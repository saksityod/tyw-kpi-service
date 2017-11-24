<?php

namespace App\Http\Controllers;

use App\CDS;
use App\DatabaseConnection;

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

class CommonDataSetController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function test_sql(Request $request)
	{
		$dbcon = DatabaseConnection::find($request->connection_id);
		if (empty($dbcon)) {
			return json()->response(['status' => 400 , 'data' => 'Database Connection not found.']);
		}
		
		if ($dbcon->database_type_id == 3) {
			$connectionInfo = array( "Database" => $dbcon->database_name, "UID" => $dbcon->user_name, "PWD" => $dbcon->password, "ReturnDatesAsStrings" => true);	
			$con = sqlsrv_connect($dbcon->ip_address.','.$dbcon->port,$connectionInfo);
			if ($con) {
				$sql = $request->sql;
				$result = sqlsrv_query($con, $sql);	
				if ($result == false) {
					return response()->json(['status' => 400 , 'data' => sqlsrv_errors()]);
				} else {
					$json = array();
					$count = 0;
					do {
						 while (($row = sqlsrv_fetch_array($result,SQLSRV_FETCH_ASSOC)) && $count < 10) {
							foreach ($row as $key => $value) { 
								$row[$key] = iconv('tis-620', 'UTF-8', $value); 
							} 							 
							$json[] = $row;
							$count++;
						 }
					} while ( sqlsrv_next_result($result) );
					
					sqlsrv_free_stmt($result);
					sqlsrv_close($con); 	
					
					return response()->json(['status' => 200 , 'data' => $json]);
				}
			} else {
				return response()->json(['status' => 200, 'data' => sqlsrv_errors()]);
			}
		} else {
			return response()->json(['status' => 400 , 'data' => 'DB Connection is not available for this database type.']);
		}
	}
	
	public function index(Request $request)
	{	
		$qinput = array();
		$query = "
			select c.cds_id, c.cds_name, a.appraisal_level_name, c.is_sql, c.is_active
			from cds c, appraisal_level a
			where c.appraisal_level_id = a.appraisal_level_id 			
		";
		
		empty($request->appraisal_level_id) ?: ($query .= " And c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		empty($request->cds_id) ?: ($query .= " And c.cds_id = ? " AND $qinput[] = $request->cds_id);
		
		$qfooter = " Order by appraisal_level_name ";
		
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
			select appraisal_level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by appraisal_level_name
		");
		return response()->json($items);
    }
	
	public function auto_cds_name(Request $request)
	{
		$qinput = array();
		$query = "
			Select cds_id, cds_name
			From cds
			Where cds_name like ?
		";
		
		$qfooter = " Order by cds_name limit 10";
		$qinput[] = '%'.$request->cds_name.'%';
		empty($request->appraisal_level_id) ?: ($query .= " and appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);
		
	}
	
	public function store(Request $request)
	{
		if ($request->is_sql == 1) {
			$validator = Validator::make($request->all(), [
				'cds_name' => 'required|max:100|unique:cds',
				'cds_desc' => 'max:255',
				'appraisal_level_id' => 'required|integer',
				'connection_id' => 'required|integer',
				'is_sql' => 'required|boolean',
				'cds_sql' => 'required',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new CDS;
				$item->cds_name = $request->cds_name;
				$item->cds_desc = $request->cds_desc;
				$item->appraisal_level_id = $request->appraisal_level_id;
				$item->connection_id = $request->connection_id;
				$item->is_sql = $request->is_sql;
				$item->cds_sql = $request->cds_sql;
				$item->is_active = $request->is_active;
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
			}
		} else {
			$validator = Validator::make($request->all(), [
				'cds_name' => 'required|max:100|unique:cds',
				'cds_desc' => 'max:255',
				'appraisal_level_id' => 'required|integer',
				'is_sql' => 'required|boolean',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new CDS;
				$item->cds_name = $request->cds_name;
				$item->cds_desc = $request->cds_desc;
				$item->appraisal_level_id = $request->appraisal_level_id;
				$item->is_sql = $request->is_sql;
				$item->is_active = $request->is_active;
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
			}		
		}
		
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($cds_id)
	{
		try {
			$item = CDS::findOrFail($cds_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'CDS not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $cds_id)
	{
		try {
			$item = CDS::findOrFail($cds_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'CDS not found.']);
		}
		
		if ($request->is_sql == 1) {
			$validator = Validator::make($request->all(), [
				'cds_name' => 'required|max:100|unique:cds,cds_name,'.$cds_id . ',cds_id',
				'cds_desc' => 'max:255',
				'appraisal_level_id' => 'required|integer',
				'connection_id' => 'required|integer',
				'is_sql' => 'required|boolean',
				'cds_sql' => 'required',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->cds_name = $request->cds_name;
				$item->cds_desc = $request->cds_desc;
				$item->appraisal_level_id = $request->appraisal_level_id;
				$item->connection_id = $request->connection_id;
				$item->is_sql = $request->is_sql;
				$item->cds_sql = $request->cds_sql;
				$item->is_active = $request->is_active;
				$item->updated_by = Auth::id();
				$item->save();
			}
		} else {
			$validator = Validator::make($request->all(), [
				'cds_name' => 'required|max:100|unique:cds,cds_name,'.$cds_id . ',cds_id',
				'cds_desc' => 'max:255',
				'appraisal_level_id' => 'required|integer',
				'is_sql' => 'required|boolean',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->cds_name = $request->cds_name;
				$item->cds_desc = $request->cds_desc;
				$item->appraisal_level_id = $request->appraisal_level_id;
				$item->is_sql = $request->is_sql;
				$item->is_active = $request->is_active;
				$item->updated_by = Auth::id();
				$item->save();
			}		
		}
		
		return response()->json(['status' => 200, 'data' => $item]);	
				
	}
	
	public function destroy($cds_id)
	{
		try {
			$item = CDS::findOrFail($cds_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'CDS not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this CDS is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
