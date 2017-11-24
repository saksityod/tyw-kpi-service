<?php

namespace App\Http\Controllers;

use App\AppraisalStructure;

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

class AppraisalStructureController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function form_list(Request $request)
	{
		$items = DB::select("
			select *
			from form_type
		");
		
		return response()->json($items);
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			SELECT structure_id, seq_no, structure_name, nof_target_score, form_id, is_active
			FROM appraisal_structure
			order by seq_no asc
		");
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'seq_no' => 'required|integer|unique:appraisal_structure', 
			'structure_name' => 'required|max:100|unique:appraisal_structure',
			'nof_target_score' => 'required|integer',
			'form_id' => 'required|integer',
			'is_active' => 'required|boolean',					
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new AppraisalStructure;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($structure_id)
	{
		try {
			$item = AppraisalStructure::findOrFail($structure_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Structure not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $structure_id)
	{
		try {
			$item = AppraisalStructure::findOrFail($structure_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Structure not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'seq_no' => 'required|integer|unique:appraisal_structure,seq_no,' . $structure_id . ',structure_id', 
			'structure_name' => 'required|max:100|unique:appraisal_structure,structure_name,' . $structure_id . ',structure_id', 
			'nof_target_score' => 'required|integer',
			'form_id' => 'required|integer',
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
	
	public function destroy($perspective_id)
	{
		try {
			$item = AppraisalStructure::findOrFail($perspective_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Structure not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Structure is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
