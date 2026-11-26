{{--
    Advanced settings: classification, supporting links and the responsible agent.
    Every other part of a property has one editor, so creation and editing use the same code:
      details & location → property editor above · composition, units, facilities, charges, documents → setup.
--}}
<section class="property-advanced-editor" aria-labelledby="advanced-editor-title">
    <div class="property-advanced-heading">
        <div><span class="property-kind">Property settings</span><h2 id="advanced-editor-title">Classification, links & agent</h2><p>Set how this property is classified, the supporting links shown with it, and the agent responsible for it.</p></div>
    </div>

    <form data-live-form method="POST" action="{{ route('complex.workflow.advanced', $complex->slug) }}" class="property-advanced-form" data-advanced-settings>
        @csrf
        <div class="row g-6">
            <div class="col-md-6">
                <label for="advanced-class" class="form-label required">Property classification</label>
                <select class="form-select" name="class" id="advanced-class" required>
                    @foreach(array_unique(array_filter([$complex->class, 'Regular', 'Affordable', 'Luxury', 'Executive'])) as $classification)
                        <option value="{{ $classification }}" @selected($complex->class === $classification)>{{ $classification }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label for="lead_assigned_agent" class="form-label">Responsible agent</label>
                @include('pages.business.agents.partials.picker', ['name' => 'assigned_agent_id', 'id' => 'lead_assigned_agent', 'selected' => $complex->assignedAgent, 'query' => ['signin' => 1], 'placeholder' => 'Search agents who sign in'])
                @if($complex->assignedAgent && !$complex->assignedAgent->user_id)<div class="form-text text-warning">{{ $complex->assignedAgent->full_name }} doesn't sign in. Choose an agent who signs in so they can work this property.</div>@endif
                <div class="form-text">{{ $complex->assignedAgent ? 'Currently '.$complex->assignedAgent->first_name.' '.$complex->assignedAgent->last_name.'. ' : 'No agent assigned. ' }}New leads for this property are assigned to this agent. Clear the selection to leave the property unassigned.</div>
            </div>
            <div class="col-12">
                <label for="advanced-links" class="form-label">Supporting links</label>
                <textarea class="form-control" name="links" id="advanced-links" rows="3" placeholder="https://…" aria-describedby="advanced-links-help">{{ $links->pluck('link')->implode("\n") }}</textarea>
                <div class="form-text" id="advanced-links-help">One web address per line, e.g. a website, virtual tour or map link. Changing links asks for the supporting-records setup step to be reviewed again.</div>
            </div>
        </div>

        <div class="property-advanced-review mt-6">
            <h4>Other property settings</h4>
            <ul class="mb-0">
                <li>Name, reference, contact and location: the <a href="{{ route('complex.show', $complex->slug) }}#settings">property editor</a>.</li>
                <li>Residential, commercial or mixed-use, and unit inventory: <a href="{{ route('complex.setup', [$complex->slug, 'step' => 'structure']) }}">property setup</a>.</li>
                <li>Facilities and parking: the <a href="{{ route('complex.show', $complex->slug) }}#amenities">Facilities</a> tab.</li>
            </ul>
        </div>

        <div class="property-advanced-footer">
            <span class="property-muted" role="status"></span>
            <button type="submit" class="btn btn-primary">Save settings</button>
        </div>
    </form>
</section>
