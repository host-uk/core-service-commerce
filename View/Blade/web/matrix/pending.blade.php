@extends('admin.layouts.app')

@section('title', 'Pending Permission Requests')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Page header --}}
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Pending Permission Requests</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Training mode: Review and approve permission requests</p>
        </div>
        @if($entity)
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Filtered by: <span class="font-medium">{{ $entity->name }}</span>
            </div>
        @endif
    </div>

    {{-- Entity filter --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('commerce.matrix.pending') }}" class="flex gap-4">
            <div class="flex-1">
                <core:select name="entity" label="Filter by Entity" x-on:change="$el.form.submit()">
                    <core:select.option value="">All Entities</core:select.option>
                    @foreach($entities as $ent)
                        <core:select.option value="{{ $ent->id }}" :selected="$entity?->id == $ent->id">
                            {{ $ent->path }} - {{ $ent->name }}
                        </core:select.option>
                    @endforeach
                </core:select>
            </div>
        </form>
    </div>

    {{-- Requests table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        @if($requests->isEmpty())
            <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                <i class="fa-solid fa-check-circle text-4xl mb-4 text-green-500"></i>
                <p class="mb-2">No pending requests</p>
                <p class="text-sm">All permission requests have been processed.</p>
            </div>
        @else
            <form action="{{ route('commerce.matrix.bulk-train') }}" method="POST">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50">
                                <th class="px-6 py-3">Entity</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">Route</th>
                                <th class="px-6 py-3">Time</th>
                                <th class="px-6 py-3 text-center">Allow</th>
                                <th class="px-6 py-3 text-center">Deny</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($requests as $index => $request)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-3">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $request->entity?->name ?? 'Unknown' }}</span>
                                        <div class="text-xs text-gray-500 font-mono">{{ $request->entity?->path }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $request->action }}</span>
                                        @if($request->scope)
                                            <span class="text-xs text-gray-500">({{ $request->scope }})</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 font-mono text-xs text-gray-500">
                                        {{ $request->method }} {{ Str::limit($request->route, 40) }}
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 text-xs">
                                        {{ $request->created_at->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <input type="hidden" name="decisions[{{ $index }}][entity_id]" value="{{ $request->entity_id }}">
                                        <input type="hidden" name="decisions[{{ $index }}][key]" value="{{ $request->action }}">
                                        <input type="hidden" name="decisions[{{ $index }}][scope]" value="{{ $request->scope }}">
                                        <core:radio name="decisions[{{ $index }}][allow]" value="1" class="text-green-600!" />
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <core:radio name="decisions[{{ $index }}][allow]" value="0" class="text-red-600!" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                    <core:button type="submit" variant="primary" icon="check">
                        Submit Training Decisions
                    </core:button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
