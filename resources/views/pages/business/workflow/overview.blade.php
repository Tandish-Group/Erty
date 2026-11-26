<section class="card dashboard-operating-path mb-0" data-workflow-overview>
    <button class="dashboard-operating-path-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard-workflow-steps" aria-expanded="false" aria-controls="dashboard-workflow-steps">
        <span class="dashboard-operating-path-icon"><i class="fas fa-route" aria-hidden="true"></i></span>
        <span class="dashboard-operating-path-copy">
            <strong>Your operating path</strong>
            <small>See the next steps from property setup to active tenancies</small>
        </span>
        <span class="dashboard-operating-path-action">View steps <i class="fas fa-chevron-down" aria-hidden="true"></i></span>
    </button>
    <div class="collapse" id="dashboard-workflow-steps">
      <div class="dashboard-operating-path-content">
        <p class="dashboard-operating-path-description">{{ config('app.name') }} keeps the next operational step visible so your team always knows what to do next.</p>
        <div class="row g-5">
            @foreach($workflowSteps as $step)
                @php
                    $statusClasses = [
                        'complete' => ['border-success', 'bg-light-success', 'text-success', 'ki-check'],
                        'next' => ['border-primary', 'bg-light-primary', 'text-primary', 'ki-arrow-right'],
                        'blocked' => ['border-warning', 'bg-light-warning', 'text-warning', 'ki-lock-2'],
                    ][$step['status']];
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="border {{ $statusClasses[0] }} rounded h-100 p-5">
                        <div class="d-flex align-items-start gap-4">
                            <span class="symbol symbol-35px flex-shrink-0">
                                <span class="symbol-label {{ $statusClasses[1] }} {{ $statusClasses[2] }} fw-bolder">
                                    {{ $step['number'] }}
                                </span>
                            </span>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <h3 class="fs-5 fw-bolder text-dark mb-0">{{ $step['title'] }}</h3>
                                    <span class="badge {{ $statusClasses[1] }} {{ $statusClasses[2] }}">{{ $step['status_label'] }}</span>
                                </div>
                                <p class="text-muted fs-7 mb-4">{{ $step['description'] }}</p>
                                <a href="{{ $step['url'] }}" class="btn btn-sm {{ $step['status'] === 'blocked' ? 'btn-light-warning' : 'btn-light-primary' }}">
                                    {{ $step['action_label'] }}
                                    <i class="ki-duotone {{ $statusClasses[3] }} fs-6 ms-2"><span class="path1"></span><span class="path2"></span></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
      </div>
    </div>
</section>
