<x-filament-panels::page>
    <div class="flex flex-col gap-8">
        @foreach ($this->getModuleGroups() as $group)
            <section class="flex flex-col gap-3">
                <h2 class="text-base font-bold text-gray-950 dark:text-white">
                    {{ $group['group'] }}
                </h2>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($group['modules'] as $module)
                        @php $isLive = filled($module['url']); @endphp
                        <a
                            @if ($isLive) href="{{ $module['url'] }}" @endif
                            @class([
                                'flex flex-col gap-3 rounded-2xl border p-4 transition',
                                'border-gray-200 bg-white hover:border-primary-300 hover:shadow-sm dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400/50' => $isLive,
                                'cursor-default border-dashed border-gray-200 bg-gray-50/60 dark:border-white/10 dark:bg-white/[0.02]' => ! $isLive,
                            ])
                        >
                            <span
                                @class([
                                    'flex h-10 w-10 items-center justify-center rounded-xl',
                                    'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' => $isLive,
                                    'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => ! $isLive,
                                ])
                            >
                                <x-filament::icon :icon="$module['icon']" class="h-5 w-5" />
                            </span>

                            <span
                                @class([
                                    'text-sm font-semibold',
                                    'text-gray-950 dark:text-white' => $isLive,
                                    'text-gray-500 dark:text-gray-400' => ! $isLive,
                                ])
                            >
                                {{ $module['label'] }}
                            </span>

                            @unless ($isLive)
                                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                    Segera hadir
                                </span>
                            @endunless
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
