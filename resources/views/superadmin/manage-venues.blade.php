@extends('layouts.planning-office')

@section('title', 'Venue Management')
@section('subheading', 'Review and manage the venues available for event.')

@section('content')
        @if($errors->any())
            <div class="od-alert od-alert--error">{{ $errors->first() }}</div>
        @endif

        <section class="od-card">
            <div class="od-card__head">
                <h2 class="od-card__title">Add Venue</h2>
            </div>
            <form method="POST" action="{{ route('planning_office.venues.store') }}" class="po-inline-form">
                @csrf
                <label class="od-field" for="venue-name">
                    <span class="od-label">Venue Name</span>
                    <input id="venue-name" type="text" name="name" required class="od-input" placeholder="Enter venue name">
                </label>
                <button type="submit" class="od-btn od-btn--primary">Add Venue</button>
            </form>
        </section>

        <section class="od-card">
            <div class="od-card__head">
                <h2 class="od-card__title">Active Venues</h2>
                <span class="od-pill">{{ $venues->count() }} venues</span>
            </div>

            <div class="od-table-wrap">
                <table class="od-table">
                    <thead>
                        <tr>
                            <th>Venue Name</th>
                            <th>Approved Events</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($venues as $venue)
                            @php
                                $venueId = data_get($venue, 'id', $loop->index + 1);
                                $venueName = data_get($venue, 'name', data_get($venue, 'venue_name'));
                                $venueDisplayName = data_get($venue, 'venue_name', $venueName);
                                $eventCount = data_get($venue, 'events_count', 0);
                            @endphp
                            <tr>
                                <td class="od-table__title">
                                    <form method="POST" action="{{ route('planning_office.venues.update', $venueId) }}" class="po-inline-form" style="flex-wrap:nowrap">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $venueName }}" aria-label="Venue name" class="od-input od-input--sm">
                                        <button type="submit" class="od-btn od-btn--ghost od-btn--sm">Save</button>
                                    </form>
                                </td>
                                <td>{{ $eventCount }}</td>
                                <td>
                                    <div class="od-table__actions">
                                        <a href="{{ route('planning_office.venues.events', ['venue' => $venueDisplayName]) }}" class="od-btn od-btn--primary od-btn--sm">View Events</a>
                                        <form method="POST" action="{{ route('planning_office.venues.destroy', $venueId) }}" onsubmit="return confirm('Delete this venue?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="od-btn od-btn--danger od-btn--sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="od-table__empty">No active venues yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
@endsection
