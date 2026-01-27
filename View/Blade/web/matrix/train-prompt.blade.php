{{-- Training Mode Permission Prompt --}}
{{-- Shown when a permission is undefined and training mode is enabled --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Permission Training - Commerce Matrix</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-900/50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-6">
        {{-- Header --}}
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Permission Not Defined</h2>
                <p class="text-sm text-amber-600 font-medium">Training Mode Active</p>
            </div>
        </div>

        {{-- Request Details --}}
        <div class="bg-gray-50 rounded-lg p-4 mb-6 font-mono text-sm space-y-2">
            <div class="flex">
                <span class="text-gray-500 w-20">Entity:</span>
                <span class="text-gray-900 font-medium">{{ $entity->name }} <span class="text-gray-400">({{ $entity->type }})</span></span>
            </div>
            <div class="flex">
                <span class="text-gray-500 w-20">Action:</span>
                <span class="text-gray-900 font-medium">{{ $result->key }}</span>
            </div>
            <div class="flex">
                <span class="text-gray-500 w-20">Scope:</span>
                <span class="text-gray-900">{{ $result->scope ?? 'global' }}</span>
            </div>
            <div class="flex">
                <span class="text-gray-500 w-20">Route:</span>
                <span class="text-gray-900">{{ $request->method() }} {{ $request->path() }}</span>
            </div>
        </div>

        {{-- Training Form --}}
        <form action="{{ route('commerce.matrix.train') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="entity_id" value="{{ $entity->id }}">
            <input type="hidden" name="key" value="{{ $result->key }}">
            <input type="hidden" name="scope" value="{{ $result->scope }}">
            <input type="hidden" name="route" value="{{ $request->fullUrl() }}">
            <input type="hidden" name="return_url" value="{{ $request->fullUrl() }}">

            {{-- Decision Buttons --}}
            <div class="flex gap-3">
                <button type="submit" name="allow" value="1"
                    class="flex-1 bg-green-600 text-white rounded-lg py-3 px-4 font-medium hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Allow
                    </span>
                </button>
                <button type="submit" name="allow" value="0"
                    class="flex-1 bg-red-600 text-white rounded-lg py-3 px-4 font-medium hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Deny
                    </span>
                </button>
            </div>

            {{-- Lock Option (only for non-root entities) --}}
            @if($entity->depth > 0)
                <div class="border-t pt-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="lock" value="1"
                            class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-600">
                            <span class="font-medium text-gray-900">Lock this permission</span>
                            <br>
                            Prevent child entities from overriding this decision
                        </span>
                    </label>
                </div>
            @endif
        </form>

        {{-- Footer --}}
        <div class="mt-6 pt-4 border-t flex items-center justify-between">
            <a href="{{ url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                ← Go back without training
            </a>
            <div class="text-xs text-gray-400">
                Commerce Matrix v1.0
            </div>
        </div>

        {{-- Entity Hierarchy Info --}}
        @if($entity->depth > 0)
            <div class="mt-4 pt-4 border-t">
                <p class="text-xs text-gray-500 mb-2">Entity Hierarchy:</p>
                <div class="text-xs text-gray-600 font-mono">
                    @php
                        $pathParts = explode('/', trim($entity->path, '/'));
                    @endphp
                    @foreach($pathParts as $index => $code)
                        @if($index > 0)
                            <span class="text-gray-400">→</span>
                        @endif
                        <span class="{{ $index === count($pathParts) - 1 ? 'text-indigo-600 font-medium' : '' }}">
                            {{ $code }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</body>
</html>
