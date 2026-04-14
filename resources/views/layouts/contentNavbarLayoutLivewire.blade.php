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
$container = ($container ?? 'container-fluid');
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
      {{-- Toast Container (global) --}}
      <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
      </div>

      <div class="drag-target"></div>
    </div>
  </div>
  <script>
    document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (payload) => {
      // payload kadang berupa array berisi 1 item tergantung versi/livewire usage
      const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
      const type = data.type || 'info'; // success|danger|warning|info|primary|secondary|dark
      const message = data.message || '...';
      const title = data.title || 'Notifikasi';
      const delay = Number(data.delay ?? 2500);

      const map = {
        success: 'bg-success',
        error: 'bg-danger',
        danger: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info',
        primary: 'bg-primary',
        secondary: 'bg-secondary',
        dark: 'bg-dark',
      };

      const bgClass = map[type] || 'bg-info';
      const iconClass =
        type === 'success' ? 'bx bx-check-circle' :
        (type === 'warning' ? 'bx bx-error-circle' :
        (type === 'danger' || type === 'error' ? 'bx bx-x-circle' : 'bx bx-bell'));

      const container = document.getElementById('toastContainer');
      if (!container) return;

      const el = document.createElement('div');
      el.className = `bs-toast toast fade ${bgClass} text-white`;
      el.setAttribute('role', 'alert');
      el.setAttribute('aria-live', 'assertive');
      el.setAttribute('aria-atomic', 'true');
      el.setAttribute('data-bs-delay', String(delay));

      el.innerHTML = `
        <div class="toast-header ${bgClass} text-white border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">${escapeHtml(message)}</div>
      `;

      container.appendChild(el);

      const toast = bootstrap.Toast.getOrCreateInstance(el, { delay });
      toast.show();

      el.addEventListener('hidden.bs.toast', () => el.remove());
    });

    function escapeHtml(str) {
      return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }
  });
  </script>

  @endsection