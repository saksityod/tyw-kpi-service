<?php

namespace App\Http\Controllers;

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

class DashboardController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	
	
	public function year_list()
	{
		$items = DB::select("
			select distinct appraisal_year from appraisal_period order by appraisal_year desc		
		");
		return response()->json($items);
	}
	
	public function month_list(Request $request)
	{
		$items = DB::select("
			select period_id, substr(monthname(start_date),1,3) as monthname
                from appraisal_period 
                where appraisal_year = ?
	        and appraisal_frequency_id = 1
                order by period_id desc		
		", array($request->appraisal_year));
		return response()->json($items);
		
	}

	public function balance_scorecard(Request $request)
	{
		$items = DB::select("
			select p.perspective_name, r.appraisal_item_id, i.appraisal_item_name, r.target_value, r.actual_value,r.score
				from appraisal_item_result r, employee e, appraisal_item i, perspective p, appraisal_structure s, form_type f
				where r.emp_code = e.emp_code
				and r.appraisal_item_id = i.appraisal_item_id
				and i.perspective_id = p.perspective_id
				and i.structure_id = s.structure_id
				and s.form_id = f.form_id
				and f.form_name = 'Quantity'
				and e.is_coporate_kpi = 1
				and r.period_id = ?
				order by i.appraisal_item_name	
		", array($request->period_id));
		return response()->json($items);
		
	}

	public function monthly_variance(Request $request)
	{
		$items = DB::select("
			select p.period_no, substr(monthname(p.start_date),1,3), r.target_value, r.actual_value,

			IF(r.score1_target_end > r.score5_target_end,
r.target_value - r.actual_value ,
r.actual_value -r.target_value ) as variance_value

from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity' 
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?
order by p.period_no	
		", array($request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}

	public function monthly_growth(Request $request)
	{
		$items = DB::select("
			select period_no, period_desc, sum(previous_year) as pyear, sum(current_year) as cyear, 
((sum(current_year) - sum(previous_year))/sum(previous_year)) * 100 as growth_percent
from
(select p.period_no, substr(monthname(p.start_date),1,3) as period_desc, r.actual_value as previous_year, 0 as current_year
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ? - 1
and r.appraisal_item_id = ?
union
select p.period_no, substr(monthname(p.start_date),1,3) as period_desc, 0 as previous_year, r.actual_value as current_year
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?) as growth
group by period_no, period_desc
order by period_no
		", array($request->appraisal_year,$request->appraisal_item_id,$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}

	public function ytd_monthly_variance(Request $request)
	{
		$items = DB::select("
			select a.period_no as a 
,month_name
,(select sum(target_value)
 from(
   select p.period_no
    , r.target_value
   from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
   where r.emp_code = e.emp_code
   and r.appraisal_item_id = i.appraisal_item_id
   and i.structure_id = s.structure_id
   and s.form_id = f.form_id
   and r.period_id = p.period_id
   and f.form_name = 'Quantity'
   and e.is_coporate_kpi = 1
   and p.appraisal_frequency_id = 1
   and p.appraisal_year = ?
   and r.appraisal_item_id = ?
 )b
 where period_no <= a
) as target_value
,(select sum(actual_value)
 from(
   select p.period_no
   , r.actual_value
   from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
   where r.emp_code = e.emp_code
   and r.appraisal_item_id = i.appraisal_item_id
   and i.structure_id = s.structure_id
   and s.form_id = f.form_id
   and r.period_id = p.period_id
   and f.form_name = 'Quantity'
   and e.is_coporate_kpi = 1
   and p.appraisal_frequency_id = 1
   and p.appraisal_year = ?
   and r.appraisal_item_id = ?
 )b
 where period_no <= a
) as actual_value
,(select sum(variance_value)
 from(
   select p.period_no,
   /*, r.target_value - r.actual_value as variance_value*/
   IF(r.score1_target_end > r.score5_target_end,
r.target_value - r.actual_value ,
r.actual_value -r.target_value ) as variance_value

   from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
   where r.emp_code = e.emp_code
   and r.appraisal_item_id = i.appraisal_item_id
   and i.structure_id = s.structure_id
   and s.form_id = f.form_id
   and r.period_id = p.period_id
   and f.form_name = 'Quantity'
   and e.is_coporate_kpi = 1
   and p.appraisal_frequency_id = 1
   and p.appraisal_year = ?
   and r.appraisal_item_id = ?
 )b
 where period_no <= a
) as variance_value
from(
  select p.period_no
   , substr(monthname(p.start_date),1,3) as month_name
  from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
  where r.emp_code = e.emp_code
  and r.appraisal_item_id = i.appraisal_item_id
  and i.structure_id = s.structure_id
  and s.form_id = f.form_id
  and r.period_id = p.period_id
  and f.form_name = 'Quantity'
  and e.is_coporate_kpi = 1
  and p.appraisal_frequency_id = 1
  and p.appraisal_year = ?
  and r.appraisal_item_id = ?
)a
order by a.period_no
		", array($request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}


	public function ytd_monthly_growth(Request $request)
	{
		$items = DB::select("
			
			

SELECT period_no
,period_desc
, sum(previous_year) as pyear
, sum(current_year) as cyear
, ((sum(current_year) - sum(previous_year))/sum(previous_year)) * 100 as growth_percent
from(
select main_previous_year.period_no as period_no
,period_desc
,(select sum(previous_year)
 from(
	select p.period_no
	, r.actual_value as previous_year
	from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
	where r.emp_code = e.emp_code
	and r.appraisal_item_id = i.appraisal_item_id
	and i.structure_id = s.structure_id
	and s.form_id = f.form_id
	and r.period_id = p.period_id
	and f.form_name = 'Quantity'
	and e.is_coporate_kpi = 1
	and p.appraisal_frequency_id = 1
	and p.appraisal_year = ? - 1
	and r.appraisal_item_id = ?
 )sub
 where sub.period_no <= main_previous_year.period_no
) as previous_year
, 0 as current_year
from(
select p.period_no
, substr(monthname(p.start_date),1,3) as period_desc
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ? - 1
and r.appraisal_item_id = ?
)main_previous_year

union

select main_current_year.period_no as period_no
,period_desc
,0 as previous_year
,(select sum(current_year)
 from(
		select p.period_no
		, r.actual_value as current_year
		from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
		where r.emp_code = e.emp_code
		and r.appraisal_item_id = i.appraisal_item_id
		and i.structure_id = s.structure_id
		and s.form_id = f.form_id
		and r.period_id = p.period_id
		and f.form_name = 'Quantity'
		and e.is_coporate_kpi = 1
		and p.appraisal_year = ?
		and r.appraisal_item_id = ?
 )sub
 where sub.period_no <= main_current_year.period_no
) as current_year
from(
select p.period_no
, substr(monthname(p.start_date),1,3) as period_desc
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?
)main_current_year

) as growth
group by period_no
,period_desc


		", array($request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}


	

	
   
}
