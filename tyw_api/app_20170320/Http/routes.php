<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
if (isset($_SERVER['HTTP_ORIGIN'])) {
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
	header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, useXDomain, withCredentials');
	//header('Keep-Alive: timeout=10, max=100');
}
// Route::get('/', function () {
    // return Response::json(array('hello' => 'hehe'));
// });

//Route::resource('authenticate', 'AuthenticateController', ['only' => ['index']]);
Route::group(['middleware' => 'cors'], function()
{	
	// Session //
	Route::get('session','AuthenticateController@index');
	Route::post('session', 'AuthenticateController@authenticate');
	Route::get('session/debug', 'AuthenticateController@debug');
	Route::delete('session', 'AuthenticateController@destroy');
	
	// Common Data Set //
	Route::get('cds/al_list','CommonDataSetController@al_list');
	Route::get('cds/connection_list','CommonDataSetController@connection_list');
	Route::post('cds/auto_cds','CommonDataSetController@auto_cds_name');
	Route::patch('cds/{id}','CommonDataSetController@update');
	Route::get('cds/{id}','CommonDataSetController@show');
	Route::delete('cds/{id}','CommonDataSetController@destroy');
	Route::post('cds','CommonDataSetController@store');
	Route::get('cds','CommonDataSetController@index');
	Route::post('cds/test_sql','CommonDataSetController@test_sql');
	
	// Appraisal Item //
	Route::post('appraisal_item','AppraisalItemController@store');
	Route::get('appraisal_item/al_list','AppraisalItemController@al_list');
	Route::get('appraisal_item/uom_list','AppraisalItemController@uom_list');	
	Route::get('appraisal_item/cds_list','AppraisalItemController@cds_list');
	Route::get('appraisal_item/perspective_list','AppraisalItemController@perspective_list');
	Route::get('appraisal_item/structure_list','AppraisalItemController@structure_list');
	Route::post('appraisal_item/auto_appraisal_name','AppraisalItemController@auto_appraisal_name');
	Route::get('appraisal_item','AppraisalItemController@index');
	Route::get('appraisal_item/{appraisal_item_id}','AppraisalItemController@show');
	Route::patch('appraisal_item/{appraisal_item_id}','AppraisalItemController@update');
	Route::delete('appraisal_item/{appraisal_item_id}','AppraisalItemController@destroy');
	
	// Import Employee //
	Route::get('import_employee/role_list','ImportEmployeeController@role_list');
	Route::get('import_employee/dep_list','ImportEmployeeController@dep_list');
	Route::get('import_employee/sec_list','ImportEmployeeController@sec_list');
	Route::get('import_employee/auto_position_name','ImportEmployeeController@auto_position_name');
	Route::get('import_employee/auto_employee_name','ImportEmployeeController@auto_employee_name');
	Route::get('import_employee/{emp_code}/role', 'ImportEmployeeController@show_role');
	Route::patch('import_employee/{emp_code}/role', 'ImportEmployeeController@assign_role');
	Route::patch('import_employee/role', 'ImportEmployeeController@batch_role');
	Route::get('import_employee','ImportEmployeeController@index');
	Route::get('import_employee/{emp_code}', 'ImportEmployeeController@show');
	Route::patch('import_employee/{emp_code}', 'ImportEmployeeController@update');
	Route::delete('import_employee/{emp_code}', 'ImportEmployeeController@destroy');
	Route::post('import_employee', 'ImportEmployeeController@import');
	
	// CDS Result //
	Route::get('cds_result/al_list','CDSResultController@al_list');
	Route::get('cds_result/year_list', 'CDSResultController@year_list');
	Route::get('cds_result/month_list', 'CDSResultController@month_list');
	Route::post('cds_result/auto_position_name', 'CDSResultController@auto_position_name');
	Route::post('cds_result/auto_emp_name', 'CDSResultController@auto_emp_name');
	Route::get('cds_result', 'CDSResultController@index');
	Route::post('cds_result/export', 'CDSResultController@export');
	Route::post('cds_result', 'CDSResultController@import');
	
	// Appraisal Data //
	Route::get('appraisal_data/structure_list','AppraisalDataController@structure_list');
	Route::get('appraisal_data/al_list','AppraisalDataController@al_list');
	Route::get('appraisal_data/period_list','AppraisalDataController@period_list');
	Route::get('appraisal_data/appraisal_type_list','AppraisalDataController@appraisal_type_list');
	Route::post('appraisal_data/auto_appraisal_item','AppraisalDataController@auto_appraisal_item');
	Route::post('appraisal_data/auto_emp_name','AppraisalDataController@auto_emp_name');
	Route::post('appraisal_data/calculate_weight','AppraisalDataController@calculate_weight');
	Route::get('appraisal_data','AppraisalDataController@index');
	Route::post('appraisal_data/export','AppraisalDataController@export');
	Route::post('appraisal_data','AppraisalDataController@import');
	
	// Appraisal Assignment //
	Route::get('appraisal_assignment/appraisal_type_list', 'AppraisalAssignmentController@appraisal_type_list');
	Route::post('appraisal_assignment/auto_position_name', 'AppraisalAssignmentController@auto_position_name');
	Route::get('appraisal_assignment/al_list', 'AppraisalAssignmentController@al_list');
	Route::get('appraisal_assignment/period_list', 'AppraisalAssignmentController@period_list');
	Route::get('appraisal_assignment/frequency_list', 'AppraisalAssignmentController@frequency_list');
	Route::post('appraisal_assignment/auto_employee_name', 'AppraisalAssignmentController@auto_employee_name');
	Route::get('appraisal_assignment', 'AppraisalAssignmentController@index');
	Route::get('appraisal_assignment/template', 'AppraisalAssignmentController@assign_template');
	Route::get('appraisal_assignment/new_assign_to', 'AppraisalAssignmentController@new_assign_to');
	Route::get('appraisal_assignment/new_action_to', 'AppraisalAssignmentController@new_action_to');
	Route::get('appraisal_assignment/edit_assign_to', 'AppraisalAssignmentController@edit_assign_to');
	Route::get('appraisal_assignment/edit_action_to', 'AppraisalAssignmentController@edit_action_to');	
	Route::get('appraisal_assignment/{emp_result_id}', 'AppraisalAssignmentController@show');	
	Route::patch('appraisal_assignment/{emp_result_id}', 'AppraisalAssignmentController@update');	
	Route::delete('appraisal_assignment/{emp_result_id}', 'AppraisalAssignmentController@destroy');	
	Route::post('appraisal_assignment', 'AppraisalAssignmentController@store');	
	
	// Appraisal //
	Route::get('appraisal/year_list', 'AppraisalController@year_list');
	Route::get('appraisal/period_list', 'AppraisalController@period_list');
	Route::get('appraisal/al_list', 'AppraisalController@al_list');
	Route::get('appraisal/dep_list','AppraisalController@dep_list');
	Route::get('appraisal/sec_list','AppraisalController@sec_list');
	Route::get('appraisal/auto_position_name','AppraisalController@auto_position_name');
	Route::get('appraisal/auto_employee_name','AppraisalController@auto_employee_name');
	Route::post('appraisal/calculate_weight','AppraisalController@calculate_weight');
	Route::get('appraisal','AppraisalController@index');
	Route::get('appraisal/edit_assign_to', 'AppraisalController@edit_assign_to');
	Route::get('appraisal/edit_action_to', 'AppraisalController@edit_action_to');		
	Route::get('appraisal/{emp_result_id}','AppraisalController@show');	
	Route::patch('appraisal/{emp_result_id}','AppraisalController@update');	
	
	// Database Connection //
	Route::get('database_connection', 'DatabaseConnectionController@index');
	Route::get('database_connection/db_type_list', 'DatabaseConnectionController@db_type_list');	
	Route::post('database_connection', 'DatabaseConnectionController@store');
	Route::get('database_connection/{connection_id}', 'DatabaseConnectionController@show');
	Route::patch('database_connection/{connection_id}', 'DatabaseConnectionController@update');
	Route::delete('database_connection/{connection_id}', 'DatabaseConnectionController@destroy');
	
	// System Config //
	Route::get('system_config', 'SystemConfigController@index');
	Route::patch('system_config', 'SystemConfigController@update');
	Route::get('system_config/month_list', 'SystemConfigController@month_list');
	Route::get('system_config/frequency_list', 'SystemConfigController@frequency_list');
	
	// Perspective //
	Route::get('perspective', 'PerspectiveController@index');
	Route::post('perspective', 'PerspectiveController@store');
	Route::get('perspective/{perspective_id}', 'PerspectiveController@show');
	Route::patch('perspective/{perspective_id}', 'PerspectiveController@update');
	Route::delete('perspective/{perspective_id}', 'PerspectiveController@destroy');	
	
	// UOM //
	Route::get('uom', 'UOMController@index');
	Route::post('uom', 'UOMController@store');
	Route::get('uom/{uom_id}', 'UOMController@show');
	Route::patch('uom/{uom_id}', 'UOMController@update');
	Route::delete('uom/{uom_id}', 'UOMController@destroy');		
	
	// Appraisal Structure //
	Route::get('appraisal_structure', 'AppraisalStructureController@index');
	Route::get('appraisal_structure/form_list', 'AppraisalStructureController@form_list');
	Route::post('appraisal_structure', 'AppraisalStructureController@store');
	Route::get('appraisal_structure/{structure_id}', 'AppraisalStructureController@show');
	Route::patch('appraisal_structure/{structure_id}', 'AppraisalStructureController@update');
	Route::delete('appraisal_structure/{structure_id}', 'AppraisalStructureController@destroy');
	
	// Threshold //
	Route::get('threshold', 'ThresholdController@index');
	Route::get('threshold/structure_list', 'ThresholdController@structure_list');
	Route::post('threshold', 'ThresholdController@store');
	Route::get('threshold/{threshold_id}', 'ThresholdController@show');
	Route::patch('threshold/{threshold_id}', 'ThresholdController@update');
	Route::delete('threshold/{threshold_id}', 'ThresholdController@destroy');			
	
	// Appraisal Level //
	Route::get('appraisal_level', 'AppraisalLevelController@index');
	Route::post('appraisal_level', 'AppraisalLevelController@store');
	Route::get('appraisal_level/{appraisal_level_id}', 'AppraisalLevelController@show');
	Route::patch('appraisal_level/{appraisal_level_id}', 'AppraisalLevelController@update');
	Route::delete('appraisal_level/{appraisal_level_id}', 'AppraisalLevelController@destroy');	
	Route::get('appraisal_level/{appraisal_level_id}/criteria', 'AppraisalLevelController@appraisal_criteria');	
	Route::patch('appraisal_level/{appraisal_level_id}/criteria', 'AppraisalLevelController@update_criteria');

	// Appraisal Grade //
	Route::get('appraisal_grade', 'AppraisalGradeController@index');
	Route::get('appraisal_grade/al_list', 'AppraisalGradeController@al_list');
	Route::post('appraisal_grade', 'AppraisalGradeController@store');
	Route::get('appraisal_grade/{grade_id}', 'AppraisalGradeController@show');
	Route::patch('appraisal_grade/{grade_id}', 'AppraisalGradeController@update');
	Route::delete('appraisal_grade/{grade_id}', 'AppraisalGradeController@destroy');	
	
	// Appraisal Period //
	Route::get('appraisal_period', 'AppraisalPeriodController@index');
	Route::get('appraisal_period/appraisal_year_list', 'AppraisalPeriodController@appraisal_year_list');
	Route::get('appraisal_period/start_month_list', 'AppraisalPeriodController@start_month_list');
	Route::get('appraisal_period/frequency_list', 'AppraisalPeriodController@frequency_list');
	Route::get('appraisal_period/add_frequency_list', 'AppraisalPeriodController@add_frequency_list');
	Route::post('appraisal_period/auto_desc', 'AppraisalPeriodController@auto_desc');
	Route::post('appraisal_period/create', 'AppraisalPeriodController@create');
	Route::post('appraisal_period', 'AppraisalPeriodController@store');
	Route::get('appraisal_period/{period_id}', 'AppraisalPeriodController@show');
	Route::patch('appraisal_period/{period_id}', 'AppraisalPeriodController@update');
	Route::delete('appraisal_period/{period_id}', 'AppraisalPeriodController@destroy');		
	
	//Dashboard //
	Route::get('dashboard/year_list', 'DashboardController@year_list');
	Route::post('dashboard/month_list', 'DashboardController@month_list');
	Route::post('dashboard/balance_scorecard', 'DashboardController@balance_scorecard');
	Route::post('dashboard/monthly_variance', 'DashboardController@monthly_variance');
	Route::post('dashboard/monthly_growth', 'DashboardController@monthly_growth');
	Route::post('dashboard/ytd_monthly_variance', 'DashboardController@ytd_monthly_variance');
	Route::post('dashboard/ytd_monthly_growth', 'DashboardController@ytd_monthly_growth');	
	
	
	//Result Bonus //
	Route::get('result_bonus/appraisal_year', 'ResultBonusController@appraisal_year');
	Route::get('result_bonus/bonus_period', 'ResultBonusController@bonus_period');
	Route::post('result_bonus/result_bonus', 'ResultBonusController@result_bonus');

	//Result Raise Amount //
	Route::get('result_raise_amount/appraisal_year', 'ResultRaiseAmountController@appraisal_year');
	Route::get('result_raise_amount/salary_period', 'ResultRaiseAmountController@salary_period');
	Route::post('result_raise_amount/result_raise_amount', 'ResultRaiseAmountController@result_raise_amount');

	
	Route::get('404', ['as' => 'notfound', function () {
		return response()->json(['status' => '404']);
	}]);

	Route::get('405', ['as' => 'notallow', function () {
		return response()->json(['status' => '405']);
	}]);	
});



