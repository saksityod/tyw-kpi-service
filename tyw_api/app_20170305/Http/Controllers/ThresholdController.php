<?php

namespace App\Http\Controllers;

use App\Threshold;

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

class ThresholdController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$qinput = array();
		$query = "
			select a.threshold_id, b.structure_name, a.target_score, a.threshold_name, a.is_active
			from threshold a
			left outer join appraisal_structure b
			on a.structure_id = b.structure_id
			left outer join form_type c
			on b.form_id = c.form_id
			where c.form_name = 'Quality'
			and b.is_active = 1		
		";
		
		empty($request->structure_id) ?: ($query .= " and a.structure_id = ? " AND $qinput[] = $request->structure_id);
		
		$qfooter = " order by b.structure_id asc, target_score asc ";
		
		$items = DB::select($query.$qfooter, $qinput);

		return response()->json($items);
	}
	
	public function structure_list()
	{
		$items = DB::select("
			select a.structure_id, a.structure_name
			from appraisal_structure a
			left outer join form_type b
			on a.form_id = b.form_id
			where b.form_name = 'Quality'
			and a.is_active = 1
		");
		
		return response()->json($items);
	}
	
	public function store(Request $request)
	{

		$validator = Validator::make($request->all(), [
			'structure_id' => 'required|integer|unique:threshold,structure_id,null,threshold_id,target_score,' . $request->target_score . ',threshold_name,' . $request->threshold_name,
			'target_score' => 'required|integer|unique:threshold,target_score,null,structure_id,structure_id,' . $request->structure_id,
			'threshold_name' => 'required|max:50|unique:threshold,threshold_name,null,structure_id,structure_id,' . $request->structure_id,
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new Threshold;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($threshold_id)
	{
		try {
			$item = Threshold::findOrFail($threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $threshold_id)
	{
		try {
			$item = Threshold::findOrFail($threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold not found.']);
		}
		
		$validator = Validator::make($request->all(), [	
			'structure_id' => 'required|integer|unique:threshold,structure_id,'. $threshold_id .',threshold_id,target_score,' . $request->target_score . ',threshold_name,' . $request->threshold_name,
			'target_score' => 'required|integer|unique:threshold,target_score,' . $threshold_id . ',threshold_id,structure_id,' . $request->structure_id,
			'threshold_name' => 'required|max:50|unique:threshold,threshold_name,' . $threshold_id . ',threshold_id,structure_id,' . $request->structure_id,
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($threshold_id)
	{
		try {
			$item = Threshold::findOrFail($threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Threshold is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
