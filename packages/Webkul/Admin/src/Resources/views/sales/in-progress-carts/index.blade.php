<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.sales.in-progress-carts.index.title')
    </x-slot>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="py-3 text-xl font-bold text-diidsInk dark:text-white">
            @lang('admin::app.sales.in-progress-carts.index.title')
        </p>

        <div class="flex items-center gap-x-2.5">
            <!-- Export Modal -->
            <x-admin::datagrid.export :src="route('admin.sales.in-progress-carts.index')" />
        </div>
    </div>

    <x-admin::datagrid :src="route('admin.sales.in-progress-carts.index')" />
</x-admin::layouts>
