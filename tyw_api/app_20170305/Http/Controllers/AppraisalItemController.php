<?php

namespace App\Http\Controllers;

use App\AppraisalItem;
use App\AppraisalStructure;
use App\CDS;
use App\KPICDSMapping;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AppraisalItemController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{	
		$qinput = array();
		$query = "
			select s.seq_no, s.structure_name, s.structure_id, i.appraisal_item_id, i.appraisal_item_name, l.appraisal_level_name,
			p.perspective_name, u.uom_name, i.max_value, i.unit_deduct_score, i.is_active, f.form_name, f.app_url, f.form_id
			from appraisal_item i
			left outer join appraisal_level l
			on i.appraisal_level_id =  l.appraisal_level_id 
			left outer join appraisal_structure s
			on i.structure_id = s.structure_id 
			left outer join perspective p
			on i.perspective_id = p.perspective_id
			left outer join uom u
			on i.uom_id = u.uom_id
			left outer join form_type f
			on s.form_id = f.form_id	
			where 1=1
		";
		
		empty($request->appraisal_level_id) ?: ($query .= " And i.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		empty($request->structure_id) ?: ($query .= " And i.structure_id = ? " AND $qinput[] = $request->structure_id);
		//empty($request->perspective_id) ?: ($query .= " And i.perspective_id = ? " AND $qinput[] = $request->perspective_id);
		if ($request->structure_id == 1) {
			empty($request->perspective_id) ?: ($query .= " And i.perspective_id = ? " AND $qinput[] = $request->perspective_id);
		} else {
		}
		empty($request->appraisal_item_id) ?: ($query .= " And i.appraisal_item_id = ? " AND $qinput[] = $request->appraisal_item_id);
		
		$qfooter = " Order by s.seq_no, appraisal_item_name ";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			
		
		$structure_template = DB::select("
			select a.structure_id, a.structure_name, b.*
			from appraisal_structure a
			left outer join form_type b
			on a.form_id = b.form_id		
			where is_active = 1
		");
		
		$groups = array();
		
		foreach ($structure_template as $s) {
			$key = $s->structure_name;
			if (!isset($groups[$key])) {
				if ($s->form_name == 'Quantity') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'Perspective',
							'column_name' => 'perspective_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'UOM',
							'column_name' => 'uom_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],						
					];
				} elseif ($s->form_name == 'Quality') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],				
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				} elseif ($s->form_name == 'Deduct Score') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Max Value',
							'column_name' => 'max_value',
							'data_type' => 'number',
						],						
						[
							'column_display' => 'Deduct Score/Unit',
							'column_name' => 'unit_deduct_score',
							'data_type' => 'number',
						],									
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				}
				$groups[$key] = array(
					'items' => array(),
					'count' => 0,
					'columns' => $columns,
					'structure_id' => $s->structure_id,
					'form_id' => $s->form_id,
					'form_url' => $s->app_url
				);
			}
		}		
		
		foreach ($itemsForCurrentPage as $item) {
			$key = $item->structure_name;
			if (!isset($groups[$key])) {
				if ($item->form_name == 'Quantity') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Structure',
							'column_name' => 'structure_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'Perspective',
							'column_name' => 'perspective_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'UOM',
							'column_name' => 'uom_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],						
					];
				} elseif ($item->form_name == 'Quality') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],				
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				} elseif ($item->form_name == 'Deduct Score') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Max Value',
							'column_name' => 'max_value',
							'data_type' => 'number',
						],						
						[
							'column_display' => 'Deduct Score/Unit',
							'column_name' => 'unit_deduct_score',
							'data_type' => 'number',
						],									
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				}
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
					'columns' => $columns,
					'structure_id' => $item->structure_id,
					'form_id' => $item->form_id,
					'form_url' => $item->app_url
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
		$resultT = $result->toArray();
		$resultT['group'] = $groups;
		return response()->json($resultT);	

	}
	
	public function connection_list()
	{
		$items = DB::select("
			Select connection_id, connection_name
			From database_connection 
			Order by connection_name		
		");
		return response()->json($items);
	}
   
    public function al_list()
    {
		$items = DB::select("
			Select appraisal_level_id, appraisal_level_name
			From appraisal_level 
			Where is_active = 1 order by appraisal_level_name
		");
		return response()->json($items);
    }
	
	public function perspective_list()
	{
		$items = DB::select("
			Select perspective_id, perspective_name
			From perspective
			Where is_active = 1 order by perspective_name		
		");
		return response()->json($items);
	}
	
	public function structure_list()
	{
		$items = DB::select("
			Select structure_id, structure_name
			From appraisal_structure
			Where is_active = 1 order by structure_name		
		");
		return response()->json($items);
	}
	
	public function uom_list()
	{
		$items = DB::select("
			Select uom_id, uom_name
			From uom
			Where is_active = 1 order by uom_name		
		");
		return response()->json($items);
	}	
	
	public function auto_appraisal_name(Request $request)
	{
		$items = DB::select("
			Select appraisal_item_id, appraisal_item_name
			From appraisal_item
			Where appraisal_level_id = ?
			And perspective_id = ?
			And structure_id = ?
			And appraisal_item_name like ?
			Order by appraisal_item_name
		", array($request->appraisal_level_id, $request->perspective_id, $request->structure_id, '%'.$request->appraisal_item_name.'%'));
		return response()->json($items);
		
	}
	
	public function show($appraisal_item_id)
	{
		try {
			$cds_ar = array();
			$cds_name_ar = array();
			$item = AppraisalItem::find($appraisal_item_id);
			$structure = AppraisalStructure::find($item->structure_id);
			empty($structure) ? $item->structure_name = '' : $item->structure_name = $structure->structure_name;
			$cds = DB::select("
				select a.cds_id, b.cds_name
				from kpi_cds_mapping a left outer join cds b
				on a.cds_id = b.cds_id
				where a.appraisal_item_id = ?
				order by a.created_dttm asc
			", array($item->appraisal_item_id));
			$key = 0;
			foreach ($cds as $c) {
				$cds_ar['{'.$key.'}'] = $c->cds_id;
				$cds_name_ar['{'.$key.'}'] = $c->cds_name;
				$key += 1;
			}
			$item->cds_id = $cds_ar;
			$item->cds_name = $cds_name_ar;
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item not found.']);
		}
		return response()->json($item);		
	}
	
	public function store(Request $request)
	{

		if ($request->form_id == 1) {
			$validator = Validator::make($request->all(), [
				'appraisal_item_name' => 'required|max:255|unique:appraisal_item',
				'appraisal_level_id' => 'required|integer',
				'perspective_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'uom_id' => 'required|integer',
				'baseline_value' => 'required|numeric|digits_between:1,15',
				'formula_desc' => 'max:1000',
				'formula_cds_id' => 'required|max:1000',
				'formula_cds_name' => 'required|max:1000',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new AppraisalItem;
				$item->fill($request->except(['form_id','cds']));
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
				// $f_cds_id = array();
				// $f_cds_name = array();
				// $key = 0;
				preg_match_all('/cds(.*?)\]/', $request->formula_cds_id, $cds);
				foreach ($cds[1] as $c) {
					$map = new KPICDSMapping;
					$map->appraisal_item_id = $item->appraisal_item_id;
					$map->cds_id = $c;
					$map->created_by = Auth::id();
					$map->save();
				}
		
				// $item->formula_cds_id = strtr($request->formula_template, $f_cds_id);
				// $item->formula_cds_name = strtr($request->formula_template, $f_cds_name);
				// $item->save();
				
			}	
		} elseif ($request->form_id == 2) {
		
			$validator = Validator::make($request->all(), [
				'appraisal_item_name' => 'required|max:255|unique:appraisal_item',
				'appraisal_level_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new AppraisalItem;
				$item->fill($request->except('form_id'));
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
			}			
		
		} elseif ($request->form_id == 3) {
		
			$validator = Validator::make($request->all(), [
				'appraisal_item_name' => 'required|max:255|unique:appraisal_item',
				'appraisal_level_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'max_value' => 'required|integer',
				'unit_deduct_score' => 'required|numeric|digits_between:1,4',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new AppraisalItem;
				$item->fill($request->except('form_id'));
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
			}				
		
		} else {
			return response()->json(['status' => 400, 'data' => 'Form not available.']);
		}
		
		return response()->json(['status' => 200, 'data' => $item]);
	}
	
	public function cds_list(Request $request)
	{
		$items = DB::select("
			Select cds_id, cds_name
			From cds
			Where appraisal_level_id = ?
			And cds_name like ?
			Order by cds_id	
		", array($request->appraisal_level_id, '%'.$request->cds_name.'%'));
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);	
		
		return response()->json($result);
		
	}	
	
	public function update(Request $request, $appraisal_item_id)
	{
		try {
			$item = AppraisalItem::findOrFail($appraisal_item_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item not found.']);
		}
		
		if ($request->form_id == 1) {
			$validator = Validator::make($request->all(), [
				'appraisal_item_name' => 'required|max:255|unique:appraisal_item,appraisal_item_name,'.$appraisal_item_id . ',appraisal_item_id',
				'appraisal_level_id' => 'required|integer',
				'perspective_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'uom_id' => 'required|integer',
				'baseline_value' => 'required|numeric|digits_between:1,15',
				'formula_desc' => 'max:1000',
				//'formula_cds_id' => 'required|max:1000',
				//'formula_cds_name' => 'required|max:1000',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->fill($request->except(['form_id','cds']));
				$item->updated_by = Auth::id();
				$item->save();
				// $f_cds_id = array();
				// $f_cds_name = array();
				// $key = 0;
				KPICDSMapping::where('appraisal_item_id',$item->appraisal_item_id)->delete();
				preg_match_all('/cds(.*?)\]/', $request->formula_cds_id, $cds);
				foreach ($cds[1] as $c) {
					$map = new KPICDSMapping;
					$map->appraisal_item_id = $item->appraisal_item_id;
					$map->cds_id = $c;
					$map->created_by = Auth::id();
					$map->save();
				}
		
				// $item->formula_cds_id = strtr($request->formula_template, $f_cds_id);
				// $item->formula_cds_name = strtr($request->formula_template, $f_cds_name);
				// $item->save();
			}	
		} elseif ($request->form_id == 2) {
		
			$validator = Validator::make($request->all(), [
				'appraisal_item_name' => 'required|max:255|unique:appraisal_item,appraisal_item_name,'.$appraisal_item_id . ',appraisal_item_id',
				'appraisal_level_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->fill($request->except('form_id'));
				$item->updated_by = Auth::id();
				$item->save();
			}			
		
		} elseif ($request->form_id == 3) {
		
			$validator = Validator::make($request->all(), [
				'appraisal_item_name' => 'required|max:255|unique:appraisal_item,appraisal_item_name,'.$appraisal_item_id . ',appraisal_item_id',
				'appraisal_level_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'max_value' => 'required|integer',
				'unit_deduct_score' => 'required|numeric|digits_between:1,4',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->fill($request->except('form_id'));
				$item->updated_by = Auth::id();
				$item->save();
			}				
		
		} else {
			return response()->json(['status' => 400, 'data' => 'Form not available.']);
		}
		
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($appraisal_item_id)
	{
		try {
			$item = AppraisalItem::findOrFail($appraisal_item_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Item is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
