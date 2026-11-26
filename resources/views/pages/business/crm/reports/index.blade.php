@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Reports & Analytics</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    @businessCan('crm_management', 'view')<a href="/customer_relationship" class="text-muted text-hover-primary">CRM</a>@else<span class="text-muted">CRM</span>@endbusinessCan
                </li>
                <li class="breadcrumb-item text-dark">Reports & Analytics</li>
            </ul>
        </div>
        <!--end::Page title-->

        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
            </div>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">

        <!--begin::Period Filter-->
        <div class="d-flex justify-content-between align-items-center mb-6">
            <div>
                <span class="text-muted fw-semibold fs-7">Showing data for the selected period</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <select id="reportPeriod" class="form-select form-select-sm form-select-solid w-150px">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="180">Last 6 months</option>
                    <option value="365">Last year</option>
                    <option value="0">All time</option>
                </select>
                <button type="button" id="btnRefreshReport" class="btn btn-sm btn-light-primary">
                    <i class="ki-duotone ki-arrows-circle fs-4">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Refresh
                </button>
            </div>
        </div>
        <!--end::Period Filter-->

        <!--begin::Summary KPIs-->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold fs-7 d-block">Total Leads</span>
                            <span class="text-gray-800 fw-bolder fs-2x" id="kpiTotalLeads">{{ $summaryStats['total_leads'] }}</span>
                        </div>
                        <div class="symbol symbol-50px">
                            <span class="symbol-label bg-light-primary">
                                <i class="ki-duotone ki-people fs-2x text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold fs-7 d-block">Converted</span>
                            <span class="text-gray-800 fw-bolder fs-2x" id="kpiConvertedLeads">{{ $summaryStats['converted_leads'] }}</span>
                            <span class="badge badge-light-success fs-9 mt-1" id="kpiConversionRate">{{ $summaryStats['conversion_rate'] }}%</span>
                        </div>
                        <div class="symbol symbol-50px">
                            <span class="symbol-label bg-light-success">
                                <i class="ki-duotone ki-check-circle fs-2x text-success"><span class="path1"></span><span class="path2"></span></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold fs-7 d-block">Lost Leads</span>
                            <span class="text-gray-800 fw-bolder fs-2x" id="kpiLostLeads">{{ $summaryStats['lost_leads'] }}</span>
                        </div>
                        <div class="symbol symbol-50px">
                            <span class="symbol-label bg-light-danger">
                                <i class="ki-duotone ki-cross-circle fs-2x text-danger"><span class="path1"></span><span class="path2"></span></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold fs-7 d-block">Avg. Conversion Time</span>
                            <span class="text-gray-800 fw-bolder fs-2x" id="kpiAvgDays">{{ $summaryStats['avg_conversion_days'] }}</span>
                            <span class="text-muted fs-8">days</span>
                        </div>
                        <div class="symbol symbol-50px">
                            <span class="symbol-label bg-light-warning">
                                <i class="ki-duotone ki-timer fs-2x text-warning"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Summary KPIs-->

        <!--begin::Charts Row 1-->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <!--begin::Lead Trend Chart-->
            <div class="col-xl-8">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Lead Trend</span>
                            <span class="text-muted fw-semibold fs-7">Monthly leads created vs converted (last 12 months)</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <canvas id="chartLeadTrend" height="300"></canvas>
                    </div>
                </div>
            </div>
            <!--end::Lead Trend Chart-->

            <!--begin::Conversion Funnel-->
            <div class="col-xl-4">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Conversion Funnel</span>
                            <span class="text-muted fw-semibold fs-7">Pipeline phase breakdown</span>
                        </h3>
                    </div>
                    <div class="card-body d-flex flex-column pt-2">
                        @foreach($conversionData['funnel'] as $phase)
                        <div class="d-flex align-items-center mb-4" id="funnel_{{ $phase['phase'] }}">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-gray-700 fw-semibold fs-7">{{ $phase['label'] }}</span>
                                    <span class="fw-bold fs-7 funnel-count">{{ $phase['count'] }}</span>
                                </div>
                                <div class="h-6px w-100 bg-light rounded">
                                    <div class="bg-{{ $phase['color'] }} rounded h-6px" style="width: {{ $conversionData['total'] > 0 ? round(($phase['count'] / $conversionData['total']) * 100) : 0 }}%;"></div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        <div class="separator separator-dashed my-3"></div>
                        <div class="d-flex justify-content-between">
                            <span class="text-gray-500 fw-semibold fs-7">Total Converted</span>
                            <span class="fw-bolder text-success fs-6" id="funnelConverted">{{ $conversionData['converted'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Conversion Funnel-->
        </div>
        <!--end::Charts Row 1-->

        <!--begin::Charts Row 2-->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <!--begin::Lead Sources-->
            <div class="col-xl-5">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Lead Sources</span>
                            <span class="text-muted fw-semibold fs-7">Where your leads come from</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex justify-content-center mb-5">
                            <canvas id="chartLeadSources" height="220"></canvas>
                        </div>
                        <div id="sourceBreakdownList">
                            @foreach($sourceData as $source)
                            <div class="d-flex align-items-center mb-3">
                                <div class="bullet w-10px h-10px rounded-circle me-3" style="background-color: {{ $source['color'] }}"></div>
                                <span class="text-gray-700 fw-semibold fs-7 flex-grow-1">{{ $source['label'] }}</span>
                                <span class="fw-bold fs-7 me-3">{{ $source['total'] }}</span>
                                <span class="badge badge-light-success fs-9">{{ $source['conversion_rate'] }}%</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Lead Sources-->

            <!--begin::Priority & Timeline-->
            <div class="col-xl-3">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Lead Priority</span>
                            <span class="text-muted fw-semibold fs-7">Temperature distribution</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2" id="priorityContainer">
                        @foreach($priorityData as $p)
                        <div class="d-flex flex-column mb-5">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge badge-light-{{ $p['color'] }} fs-7 fw-bold">{{ $p['label'] }}</span>
                                <span class="fw-bold fs-7">{{ $p['total'] }}</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1 me-3">
                                    <div class="h-5px w-100 bg-light rounded">
                                        <div class="bg-{{ $p['color'] }} rounded h-5px" style="width: {{ $p['conversion_rate'] }}%;"></div>
                                    </div>
                                </div>
                                <span class="text-muted fs-8">{{ $p['conversion_rate'] }}% conv.</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <!--end::Priority-->

            <!--begin::Task Metrics-->
            <div class="col-xl-4">
                <div class="card card-flush h-100 rounded-3">
                    <div class="card-header pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Task Performance</span>
                            <span class="text-muted fw-semibold fs-7">Overall task metrics</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-3 mb-5">
                            <div class="col-6">
                                <div class="border border-dashed border-gray-300 rounded text-center py-4 px-3">
                                    <span class="fs-2x fw-bolder text-gray-800" id="taskCompletionRate">{{ $taskData['completion_rate'] }}%</span>
                                    <div class="text-muted fs-8 fw-semibold">Completion Rate</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border border-dashed border-gray-300 rounded text-center py-4 px-3">
                                    <span class="fs-2x fw-bolder text-danger" id="taskOverdueRate">{{ $taskData['overdue_rate'] }}%</span>
                                    <div class="text-muted fs-8 fw-semibold">Overdue Rate</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border border-dashed border-gray-300 rounded text-center py-4 px-3">
                                    <span class="fs-2x fw-bolder text-warning" id="taskSlaBreachRate">{{ $taskData['sla_breach_rate'] }}%</span>
                                    <div class="text-muted fs-8 fw-semibold">SLA Breach Rate</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border border-dashed border-gray-300 rounded text-center py-4 px-3">
                                    <span class="fs-2x fw-bolder text-info" id="taskAvgHours">{{ $taskData['avg_completion_hours'] }}</span>
                                    <div class="text-muted fs-8 fw-semibold">Avg. Hours</div>
                                </div>
                            </div>
                        </div>
                        <div class="separator separator-dashed my-4"></div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fs-7">Completed</span>
                            <span class="fw-bold text-success" id="taskCompleted">{{ $taskData['completed'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fs-7">Pending</span>
                            <span class="fw-bold text-warning" id="taskPending">{{ $taskData['pending'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fs-7">In Progress</span>
                            <span class="fw-bold text-info" id="taskInProgress">{{ $taskData['in_progress'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fs-7">Overdue</span>
                            <span class="fw-bold text-danger" id="taskOverdue">{{ $taskData['overdue'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fs-7">Blocked</span>
                            <span class="fw-bold text-dark" id="taskBlocked">{{ $taskData['blocked'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Task Metrics-->
        </div>
        <!--end::Charts Row 2-->

        <!--begin::Agent Performance Table-->
        <div class="card card-flush mb-10 rounded-3">
            <div class="card-header pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Agent Performance</span>
                    <span class="text-muted fw-semibold fs-7">Conversion rates and task metrics by agent</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="agentPerformanceTable">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-150px">Agent</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Total Leads</th>
                                <th class="text-center">Active</th>
                                <th class="text-center">Converted</th>
                                <th class="text-center">Conv. Rate</th>
                                <th class="text-center">Tasks Done</th>
                                <th class="text-center">Overdue</th>
                                <th class="text-center">Task Rate</th>
                                <th class="text-center">Avg. Hours</th>
                            </tr>
                        </thead>
                        <tbody id="agentTableBody">
                            @forelse($agentData as $agent)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-35px symbol-circle me-3">
                                            <span class="symbol-label bg-light-primary text-primary fw-bold">{{ substr($agent['name'], 0, 1) }}</span>
                                        </div>
                                        <span class="text-gray-800 fw-bold">{{ $agent['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $agent['type'] === 'employee' ? 'primary' : ($agent['type'] === 'agency' ? 'info' : 'warning') }}">{{ ucfirst($agent['type']) }}</span>
                                </td>
                                <td class="text-center fw-bold">{{ $agent['total_leads'] }}</td>
                                <td class="text-center">{{ $agent['active_leads'] }}</td>
                                <td class="text-center text-success fw-bold">{{ $agent['converted_leads'] }}</td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $agent['conversion_rate'] >= 50 ? 'success' : ($agent['conversion_rate'] >= 25 ? 'warning' : 'danger') }}">{{ $agent['conversion_rate'] }}%</span>
                                </td>
                                <td class="text-center">{{ $agent['tasks_completed'] }}/{{ $agent['total_tasks'] }}</td>
                                <td class="text-center text-danger">{{ $agent['tasks_overdue'] }}</td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $agent['task_completion_rate'] >= 80 ? 'success' : ($agent['task_completion_rate'] >= 50 ? 'warning' : 'danger') }}">{{ $agent['task_completion_rate'] }}%</span>
                                </td>
                                <td class="text-center text-muted">{{ $agent['avg_completion_hours'] }}h</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">No active agents found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Agent Performance Table-->

    </div>
</div>
<!--end::Content-->

@endsection

