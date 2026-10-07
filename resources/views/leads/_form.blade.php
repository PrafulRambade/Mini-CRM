@php
    /** @var \App\Models\Lead $lead */
    $locked = $lead->isConverted();
    $currentStatus = old('status', $lead->status?->value ?? \App\Enums\LeadStatus::New->value);
    $currentSource = old('source', $lead->source?->value);
    $sourceIcons = ['web' => 'bi-globe2', 'ads' => 'bi-megaphone', 'referral' => 'bi-people'];
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="form-section-title">Contact information</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-person"></i>
                            <input type="text" id="name" name="name" value="{{ old('name', $lead->name) }}" maxlength="255" required
                                   class="form-control @error('name') is-invalid @enderror" placeholder="Jane Doe">
                        </div>
                        @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="company" class="form-label">Company</label>
                        <div class="input-icon">
                            <i class="bi bi-building"></i>
                            <input type="text" id="company" name="company" value="{{ old('company', $lead->company) }}" maxlength="255"
                                   class="form-control @error('company') is-invalid @enderror" placeholder="Acme Inc.">
                        </div>
                        @error('company') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email" name="email" value="{{ old('email', $lead->email) }}" maxlength="255" required
                                   class="form-control @error('email') is-invalid @enderror" placeholder="jane@acme.com">
                        </div>
                        @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-telephone"></i>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $lead->phone) }}" maxlength="20" required
                                   class="form-control @error('phone') is-invalid @enderror" placeholder="+91 98765 43210">
                        </div>
                        @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="form-section-title">Notes</h2>
                <textarea id="notes" name="notes" rows="6" maxlength="5000" aria-label="Notes"
                          class="form-control @error('notes') is-invalid @enderror"
                          placeholder="Context, requirements, next steps…">{{ old('notes', $lead->notes) }}</textarea>
                <div class="d-flex justify-content-between">
                    @error('notes') <div class="invalid-feedback d-block">{{ $message }}</div> @else <span></span> @enderror
                    <div class="form-text">Max 5,000 characters</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="form-section-title">Pipeline</h2>

                <div class="mb-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    @if ($locked)
                        {{-- Converted leads keep their "Won" status. --}}
                        <input type="hidden" name="status" value="{{ $lead->status->value }}">
                        <div class="form-control d-flex align-items-center gap-2 bg-transparent">
                            <x-status-badge :status="$lead->status" /> <span class="small text-muted">Locked after conversion</span>
                        </div>
                    @else
                        <select id="status" name="status" required class="form-select @error('status') is-invalid @enderror">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($currentStatus === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <div class="form-text"><i class="bi bi-lightning-charge"></i> Choosing <strong>Won</strong> converts this lead into a customer.</div>
                    @endif
                    @error('status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <span class="form-label d-block">Source <span class="text-danger">*</span></span>
                    <div class="d-flex gap-2" role="radiogroup" aria-label="Source">
                        @foreach ($sources as $source)
                            <input type="radio" class="btn-check" name="source" id="source-{{ $source->value }}" value="{{ $source->value }}"
                                   autocomplete="off" @checked($currentSource === $source->value)>
                            <label class="btn btn-light-soft flex-fill flex-column py-2 gap-1 small" for="source-{{ $source->value }}">
                                <i class="bi {{ $sourceIcons[$source->value] ?? 'bi-dot' }} fs-5"></i>{{ $source->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('source') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="follow_up_date" class="form-label">Follow-up date</label>
                    <div class="input-icon">
                        <i class="bi bi-calendar-event"></i>
                        <input type="date" id="follow_up_date" name="follow_up_date"
                               value="{{ old('follow_up_date', $lead->follow_up_date?->toDateString()) }}"
                               @unless($lead->exists) min="{{ now()->toDateString() }}" @endunless
                               class="form-control @error('follow_up_date') is-invalid @enderror">
                    </div>
                    @error('follow_up_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                @if (auth()->user()->isAdmin())
                    <div>
                        <label for="assigned_to" class="form-label">Owner</label>
                        <select id="assigned_to" name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror">
                            <option value="">Unassigned</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected((string) old('assigned_to', $lead->assigned_to) === (string) $assignee->id)>
                                    {{ $assignee->name }} — {{ $assignee->role->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_to') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                @else
                    <div class="d-flex align-items-center gap-2 small text-muted">
                        <x-avatar :name="auth()->user()->name" size="sm" /> Owned by you
                    </div>
                @endif
            </div>
        </div>

        <style>
            .btn-check:checked + .btn-light-soft { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
        </style>
    </div>
</div>
