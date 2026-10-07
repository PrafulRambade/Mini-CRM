<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\Lead\IndexLeadRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(IndexLeadRequest $request): View
    {
        $filters = $request->filters();

        $leads = Lead::query()
            ->with(['assignee', 'customer'])
            ->visibleTo($request->user())
            ->filter($filters)
            ->sorted($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'filters' => $filters,
            'sort' => $request->sort() ?? 'created_at',
            'direction' => $request->direction(),
            ...$this->formOptions($request),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Lead::class);

        return view('leads.create', ['lead' => new Lead, ...$this->formOptions($request)]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $lead = $this->leads->create($request->validated(), $request->user());

        return redirect()->route('leads.show', $lead)->with('success', $this->savedMessage($lead, 'created'));
    }

    public function show(Lead $lead): View
    {
        Gate::authorize('view', $lead);

        return view('leads.show', ['lead' => $lead->load(['assignee', 'creator', 'customer'])]);
    }

    public function edit(Request $request, Lead $lead): View
    {
        Gate::authorize('update', $lead);

        return view('leads.edit', ['lead' => $lead, ...$this->formOptions($request)]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $wasConverted = $lead->isConverted();

        $lead = $this->leads->update($lead, $request->validated(), $request->user());

        $message = ! $wasConverted && $lead->isConverted()
            ? $this->savedMessage($lead, 'updated')
            : 'Lead updated successfully.';

        return redirect()->route('leads.show', $lead)->with('success', $message);
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('convert', $lead);

        $this->leads->convert($lead, $request->user());

        return redirect()->route('leads.show', $lead)
            ->with('success', "Lead converted to customer \"{$lead->customer->name}\".");
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $this->leads->delete($lead);

        return redirect()->route('leads.index')->with('success', 'Lead deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'statuses' => LeadStatus::cases(),
            'sources' => LeadSource::cases(),
            'assignees' => $request->user()->isAdmin()
                ? User::assignable()->get(['id', 'name', 'role'])
                : collect(),
        ];
    }

    private function savedMessage(Lead $lead, string $verb): string
    {
        return $lead->isConverted()
            ? "Lead {$verb} and converted to customer \"{$lead->customer->name}\"."
            : "Lead {$verb} successfully.";
    }
}
