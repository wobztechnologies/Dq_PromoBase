@if($imageUrl ?? null)
<div class="mt-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $label ?? 'Image' }}</label>
    <div class="relative inline-block">
        <img src="{{ $imageUrl }}" alt="{{ $label ?? 'Image' }}" class="max-w-xs rounded-lg shadow-md border border-gray-200">
    </div>
</div>
@endif

