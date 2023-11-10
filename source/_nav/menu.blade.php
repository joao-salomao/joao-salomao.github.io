@php
    $links = [
        [
            "name" => "Posts",
            "url" => "/blog",
        ],
        [
            "name" => "Talks",
            "url" => "/talks",
        ],
        [
            "name" => "Projects",
            "url" => "/projects",
        ],
        [
            "name" => "About",
            "url" => "/about",
        ],
    ];
@endphp

<nav class="hidden lg:flex items-center justify-end text-lg">
    @foreach($links as $link)
        <a title="{{ $page->siteName }} {{ $link['name']  }}" href="{{ $link['url'] }}"
           class="ml-6 text-gray-700 hover:text-blue-600 {{ $page->isActive($link['url']) ? 'active text-blue-600' : '' }}">
            {{ $link['name']  }}
        </a>
    @endforeach
</nav>
