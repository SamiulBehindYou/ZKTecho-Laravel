<div class="space-y-4">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium">Name</label>
        <input type="text" name="name" id="name" required
               value="{{ old('name', $device->name ?? '') }}"
               placeholder="e.g. Main entrance"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="col-span-2">
            <label for="ip" class="mb-1 block text-sm font-medium">IP address</label>
            <input type="text" name="ip" id="ip" required
                   value="{{ old('ip', $device->ip ?? '') }}"
                   placeholder="e.g. 192.168.8.201"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            <p class="mt-1 text-xs text-gray-500">Find it on the device under Menu → Comm → Ethernet.</p>
        </div>
        <div>
            <label for="port" class="mb-1 block text-sm font-medium">Port <span class="font-normal text-gray-400">(optional)</span></label>
            <input type="number" name="port" id="port" min="1" max="65535"
                   value="{{ old('port', $device->port ?? '') }}"
                   placeholder="4370"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            <p class="mt-1 text-xs text-gray-500">Defaults to 4370.</p>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $device->is_active ?? true))
               class="rounded border-gray-300">
        Active (included in "sync all")
    </label>
</div>
