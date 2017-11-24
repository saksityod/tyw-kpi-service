<?php

namespace App\Http\Controllers;

use App\AppraisalLevel;
use App\AppraisalCriteria;

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

class AppraisalLevelController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			SELECT appraisal_level_id, appraisal_level_name, is_all_employee, is_active
			FROM appraisal_level
			order by appraisal_level_name
		");
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'appraisal_level_name' => 'required|max:100|unique:appraisal_level',
			'is_all_employee' => 'required|boolean',
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new AppraisalLevel;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($appraisal_level_id)
	{
		try {
			$item = AppraisalLevel::findOrFail($appraisal_level_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Level not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $appraisal_level_id)
	{
		try {
			$item = AppraisalLevel::findOrFail($appraisal_level_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Level not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'appraisal_level_name' => 'required|max:100|unique:appraisal_level,appraisal_level_name,' . $appraisal_level_id . ',appraisal_level_id',
			'is_all_employee' => 'required|boolean',
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
	
	public function destroy($appraisal_level_id)
	{
		try {
			$item = AppraisalLevel::findOrFail($appraisal_level_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Level not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Level is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
	
	public function appraisal_criteria($appraisal_level_id)
	{
		$items = DB::select("
			SELECT a.structure_id, a.seq_no, a.structure_name, b.weight_percent
			FROM appraisal_structure a
			left outer join appraisal_criteria b
			on a.structure_id = b.structure_id
			and b.appraisal_level_id = ?
			where a.is_active = 1
			order by a.seq_no		
		", array($appraisal_level_id));
		
		return response()->json($items);
	}
	
	public function update_criteria(Request $request, $appraisal_level_id)
	{
		try {
			$item = AppraisalLevel::findOrFail($appraisal_level_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Level not found.']);
		}	
		$total_weight = 0;
		
		foreach ($request->criteria as $c) {
			$total_weight += $c['weight_percent'];		
		}
		
		if ($total_weight != 100) {
			return response()->json(['status' => 400, 'data' => 'Total weight is not equal to 100%']);
		}
		
		foreach ($request->criteria as $c) {
			$criteria = AppraisalCriteria::where('appraisal_level_id',$appraisal_level_id)->where('structure_id',$c['structure_id']);
			if ($criteria->count() > 0) {
				AppraisalCriteria::where('appraisal_level_id',$appraisal_level_id)->where('structure_id',$c['structure_id'])->update(['weight_percent' => $c['weight_percent'], 'updated_by' => Auth::id()]);
			} else {
				$item = new AppraisalCriteria;
				$item->appraisal_level_id = $appraisal_level_id;
				$item->structure_id = $c['structure_id'];
				$item->weight_percent = $c['weight_percent'];
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
			}
		}
		
		return response()->json(['status' => 200]);
		
	}
}
