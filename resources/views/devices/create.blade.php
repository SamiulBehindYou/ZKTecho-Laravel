@extends('layouts.app')

@section('title', 'Add device')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="mb-6 text-2xl font-semibold">Add device</h1>

        <form method="POST" action="{{ route('devices.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @include('devices._form')

            <div class="mt-6 flex gap-3">
                <button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save device</button>
                <a href="{{ route('devices.index') }}" class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
@endsection
