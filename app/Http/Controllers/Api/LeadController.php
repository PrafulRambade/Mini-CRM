<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\IndexLeadRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(IndexLeadRequest $request): AnonymousResourceCollection
    {
        $leads = Lead::query()
            ->with(['assignee', 'customer'])
            ->visibleTo($request->user())
            ->filter($request->filters())
            ->sorted($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();

        return LeadResource::collection($leads);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->leads->create($request->validated(), $request->user());

        return (new LeadResource($lead))->response()->setStatusCode(201);
    }

    public function show(Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);

        return new LeadResource($lead->load(['assignee', 'customer']));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        return new LeadResource($this->leads->update($lead, $request->validated(), $request->user()));
    }

    public function convert(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('convert', $lead);

        return new LeadResource($this->leads->convert($lead, $request->user()));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);

        $this->leads->delete($lead);

        return response()->json(null, 204);
    }
}
