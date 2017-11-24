<?php

namespace App\Http\Controllers;

use App\CDS;
use App\AppraisalItemResult;

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

class AppraisalDataController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function structure_list()
	{
		$items = DB::select("
			Select s.structure_id, s.structure_name
			From appraisal_structure s, form_type t
			Where s.form_id = t.form_id
			And t.form_name = 'Deduct Score'
			And s.is_active = 1 order by structure_name
		");
		return response()->json($items);
	}
	
	public function period_list()
	{
		$items = DB::select("
			select period_id, appraisal_period_desc
			From appraisal_period
			Where appraisal_year = (select current_appraisal_year from system_config where config_id = 1)
		");
		return response()->json($items);
	}	
	
    public function al_list()
    {
		$items = DB::select("
			select appraisal_level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by appraisal_level_name
		");
		return response()->json($items);
    }
	
	public function appraisal_type_list()
	{
		$items = DB::select("
			select *
			from appraisal_type		
		");
		return response()->json($items);
	}
		
	public function auto_appraisal_item(Request $request)
	{
		$qinput = array();
		$query = "
			Select appraisal_item_id, appraisal_item_name
			From appraisal_item
			Where appraisal_item_name like ?
		";
		
		$qfooter = " Order by appraisal_item_name limit 10 ";
		$qinput[] = '%'.$request->appraisal_item_name.'%';
		empty($request->structure_id) ?: ($query .= " and structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->appraisal_level_id) ?: ($query .= " and appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);		
	}
	
	public function auto_emp_name(Request $request)
	{
		$items = DB::select("
			Select distinct e.emp_code, e.emp_name
			From employee e, emp_level l
			Where e.emp_code = l.emp_code 
			And e.emp_name like ? and e.is_active = 1
			Order by e.emp_name	limit 10
		", array('%'.$request->emp_name.'%'));
		return response()->json($items);
	}
	
	public function import(Request $request)
	{
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();			
			foreach ($items as $i) {
							
				$validator = Validator::make($i->toArray(), [
					'emp_result_id' => 'required|integer',
					'employee_code' => 'required|max:50',
					'period_id' => 'required|integer',
					'appraisal_item_id' => 'required|integer',
					'data_value' => 'required|numeric|digits_between:1,15',
				]);

				if ($validator->fails()) {
					$errors[] = ['employee_code' => $i->employee_code, 'errors' => $validator->errors()];
				} else {
					try {
						AppraisalItemResult::where("emp_result_id",$i->emp_result_id)->where("emp_code",$i->employee_code)->where("period_id",$i->period_id)->where('appraisal_item_id',$i->appraisal_item_id)->update(['actual_value' => $i->data_value, 'updated_by' => Auth::id()]);
					} catch (Exception $e) {
						$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
					}

				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}		
	
	public function export(Request $request)
	{
		$qinput = array();
		$query = "
			select p.appraisal_period_desc, p.period_id, s.structure_name, s.structure_id, i.appraisal_item_id, i.appraisal_item_name, r.emp_code, e.emp_name, r.actual_value, er.emp_result_id, t.appraisal_type_id, t.appraisal_type_name
			from appraisal_item_result r, employee e, appraisal_period p, appraisal_item i, appraisal_structure s, form_type f, emp_result er, appraisal_type t
			where r.emp_code = e.emp_code 
			and r.period_id = p.period_id
			and r.appraisal_item_id = i.appraisal_item_id
			and i.structure_id = s.structure_id
			and r.emp_result_id = er.emp_result_id
			and er.appraisal_type_id = t.appraisal_type_id
			and s.form_id = f.form_id
			and f.form_name = 'Deduct Score'			
		";
			
		empty($request->structure_id) ?: ($query .= " AND i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->appraisal_level_id) ?: ($query .= " And i.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		empty($request->appraisal_type_id) ?: ($query .= " And t.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		empty($request->appraisal_item_id) ?: ($query .= " And r.appraisal_item_id = ? " AND $qinput[] = $request->appraisal_item_id);
		empty($request->period_id) ?: ($query .= " And r.period_id = ? " AND $qinput[] = $request->postion_code);
		empty($request->emp_code) ?: ($query .= " And r.emp_code = ? " AND $qinput[] = $request->emp_code);
		
		$qfooter = " Order by r.period_id, s.structure_name, i.appraisal_item_name, r.emp_code ";
		
		$items = DB::select($query . $qfooter, $qinput);

		$filename = "Appraisal_Data";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename) {
			$excel->sheet($filename, function($sheet) use($items) {
				
				$sheet->appendRow(array('Emp Result ID', 'Appraisal Type ID', 'Appraisal Type Name', 'Employee Code', 'Structure ID', 'Structure Name', 'Period ID', 'Period Name', 'Appraisal Item ID', 'Appraisal Item Name', 'Data Value'));
		
				foreach ($items as $i) {
					$sheet->appendRow(array(
						$i->emp_result_id,
						$i->appraisal_type_id,
						$i->appraisal_type_name,
						$i->emp_code, 
						$i->structure_id, 
						$i->structure_name, 
						$i->period_id, 
						$i->appraisal_period_desc,
						$i->appraisal_item_id,
						$i->appraisal_item_name,
						$i->actual_value
						));
				}
			});

		})->export('xls');				
	}		

	
	public function index(Request $request)
	{

		$qinput = array();
		$query = "
			select p.appraisal_period_desc, s.structure_name, i.appraisal_item_name, r.emp_code, e.emp_name, r.actual_value, er.emp_result_id, atype.appraisal_type_id, atype.appraisal_type_name
			from appraisal_item_result r, employee e, appraisal_period p, appraisal_item i, appraisal_structure s, form_type f, emp_result er, appraisal_type atype
			where r.emp_code = e.emp_code 
			and r.period_id = p.period_id
			and r.appraisal_item_id = i.appraisal_item_id
			and i.structure_id = s.structure_id
			and r.emp_result_id = er.emp_result_id
			and er.appraisal_type_id = atype.appraisal_type_id
			and s.form_id = f.form_id			
			and f.form_name = 'Deduct Score'
		"; 
			
		empty($request->structure_id) ?: ($query .= " AND i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->appraisal_level_id) ?: ($query .= " And i.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		empty($request->appraisal_type_id) ?: ($query .= " And atype.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		empty($request->appraisal_item_id) ?: ($query .= " And r.appraisal_item_id = ? " AND $qinput[] = $request->appraisal_item_id);
		empty($request->period_id) ?: ($query .= " And r.period_id = ? " AND $qinput[] = $request->period_id);
		empty($request->emp_code) ?: ($query .= " And r.emp_code = ? " AND $qinput[] = $request->emp_code);
		
		$qfooter = " Order by r.period_id, s.structure_name, i.appraisal_item_name, r.emp_code ";
		
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


		return response()->json($result);
	}
	
}
