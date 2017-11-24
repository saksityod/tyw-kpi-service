<?php

namespace App\Http\Controllers;

use App\SystemConfiguration;

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

class SystemConfigController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
    public function index()
    {
		try {
			$item = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		return response()->json($item);
    }
	
	public function month_list()
	{
		$items = DB::select("
			select month_id, month_name
			from period_month
			order by month_id asc
		");
		return response()->json($items);
	}
	
	public function frequency_list()
	{
		$items = DB::select("
			select frequency_id, frequency_name
			from appraisal_frequency
			order by frequency_id asc
		");
		return response()->json($items);	
	}
	
	public function update(Request $request)
	{
		try {
			$item = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		$validator = Validator::make($request->all(), [
			'current_appraisal_year' => 'required|integer',
			'period_start_month_id' => 'required|integer',
			'appraisal_frequency_id' => 'required|integer',
			'bonus_frequency_id' => 'required|integer',
			'bonus_prorate' => 'required|max:100',
			'bonus_rate' => 'required|numeric|digits_between:1,5',
			'nof_date_bonus' => 'required|max:100',
			'salary_raise_frequency_id' => 'required|integer'
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
	
}
