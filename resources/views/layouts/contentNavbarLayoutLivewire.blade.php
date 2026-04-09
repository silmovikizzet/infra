@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

@extends('layouts/commonMaster')

@php
/* Display elements */
$contentNavbar = $contentNavbar ?? true;
$containerNav = $containerNav ?? 'container-xxl';
$isNavbar = $isNavbar ?? true;
$isMenu = $isMenu ?? true;
$isFlex = $isFlex ?? false;
$isFooter = $isFooter ?? true;
$customizerHidden = $customizerHidden ?? '';

/* Content classes */
$container = ($container ?? 'container-xxl');
@endphp

@section('title', $title ?? '')

@section('vendor-script')
    {{-- optional, bisa diisi dari component via $vendorScriptVite --}}
    @if(!empty($vendorScriptVite))
        @vite($vendorScriptVite)
    @endif
@endsection

@section('layoutContent')
<div class="layout-wrapper layout-content-navbar {{ $isMenu ? '' : 'layout-without-menu' }}">
  <div class="layout-container">

    @if ($isMenu)
      @include('layouts/sections/menu/verticalMenu')
    @endif

    <div class="layout-page">

      @if ($isNavbar)
        @include('layouts/sections/navbar/navbar')
      @endif

      <div class="content-wrapper">
        @if ($isFlex)
          <div class="{{ $container }} d-flex align-items-stretch flex-grow-1 p-0">
        @else
          <div class="{{ $container }} flex-grow-1 container-p-y">
        @endif

            {{-- ✅ Livewire v3 slot --}}
            {{ $slot }}

            {{-- (opsional) kalau mau tetap support blade page biasa --}}
            {{-- @yield('content') --}}

          </div>

        @if ($isFooter)
          @include('layouts/sections/footer/footer')
        @endif

        <div class="content-backdrop fade"></div>
      </div>
    </div>

    @if ($isMenu)
      <div class="layout-overlay layout-menu-toggle"></div>
    @endif

    <div class="drag-target"></div>
  </div>
</div>
@endsection
