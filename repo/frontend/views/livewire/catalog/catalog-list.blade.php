<div>
    {{-- Search & Filters --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Service Catalog</h1>
            @can('create', App\Models\Service::class)
                <a href="{{ route('services.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    + New Service
                </a>
            @endcan
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
            {{-- Search --}}
            <div>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search services by title or description..."
                       class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors">
            </div>

            {{-- Filter Row --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <select wire:model.live="category" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="">All Categories</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="serviceType" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="">All Types</option>
                    @foreach ($serviceTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="audience" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="">All Audiences</option>
                    @foreach ($audiences as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="tag" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="">All Tags</option>
                    @foreach ($tags as $slug => $name)
                        <option value="{{ $slug }}">{{ $name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="priceFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="">Any Price</option>
                    <option value="free">Free</option>
                    <option value="paid">Fee-Based</option>
                </select>

                <select wire:model.live="sortBy" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="earliest">Earliest Available</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="title">Alphabetical</option>
                </select>
            </div>

            @if ($search || $category || $audience || $serviceType || $tag || $priceFilter)
                <button wire:click="clearFilters" class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">
                    Clear all filters
                </button>
            @endif
        </div>
    </div>

    {{-- Loading skeleton --}}
    <div wire:loading.delay class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @for ($i = 0; $i < 6; $i++)
            <div class="bg-white rounded-xl border border-gray-200 p-6 animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-3"></div>
                <div class="h-3 bg-gray-100 rounded w-full mb-2"></div>
                <div class="h-3 bg-gray-100 rounded w-2/3 mb-4"></div>
                <div class="flex gap-2">
                    <div class="h-5 bg-gray-100 rounded w-16"></div>
                    <div class="h-5 bg-gray-100 rounded w-16"></div>
                </div>
            </div>
        @endfor
    </div>

    {{-- Results --}}
    <div wire:loading.remove>
        @if ($services->isEmpty())
            <div class="text-center py-16">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900">No services found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filters.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($services as $service)
                    <div class="bg-white rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-md transition-all p-6 flex flex-col">
                        <div class="flex items-start justify-between mb-3">
                            <a href="{{ route('services.show', $service) }}" class="text-base font-semibold text-gray-900 hover:text-indigo-600 transition-colors line-clamp-2">
                                {{ $service->title }}
                            </a>
                            <button wire:click="toggleFavorite({{ $service->id }})"
                                    class="flex-shrink-0 ml-2 p-1 rounded-full hover:bg-gray-100 transition-colors"
                                    title="{{ in_array($service->id, $favoriteIds) ? 'Remove from favorites' : 'Add to favorites' }}">
                                @if (in_array($service->id, $favoriteIds))
                                    <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-gray-300 hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                @endif
                            </button>
                        </div>

                        <p class="text-sm text-gray-500 line-clamp-2 mb-4 flex-grow">{{ $service->description }}</p>

                        {{-- Tags --}}
                        @if ($service->tags->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                @foreach ($service->tags->take(3) as $tag)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                                        {{ $tag->name }}
                                    </span>
                                @endforeach
                                @if ($service->tags->count() > 3)
                                    <span class="text-xs text-gray-400">+{{ $service->tags->count() - 3 }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- Footer --}}
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <span class="text-sm font-medium {{ $service->is_free ? 'text-green-600' : 'text-gray-900' }}">
                                {{ $service->is_free ? 'Free' : '$' . number_format($service->price, 2) }}
                            </span>
                            @php $nextSlot = $service->timeSlots->first(); @endphp
                            @if ($nextSlot)
                                <span class="text-xs text-gray-500">
                                    Next: {{ $nextSlot->start_time->format('M d, g:i A') }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">No slots available</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</div>
