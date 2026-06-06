<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('meta_description')">
    <meta name="keywords" content="@yield('meta_keywords')">
    <meta property="og:title" content="@yield('og_title')">
    <meta property="og:description" content="@yield('og_description')">
    <meta property="og:type" content="@yield('og_type')">
    <meta name="twitter:title" content="@yield('twitter_title')">
    <meta name="twitter:description" content="@yield('twitter_description')">
    @yield('meta_extra')
    @stack('structured-data')
    @stack('head')
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
