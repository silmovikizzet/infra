@isset($pageConfigs)
  {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

@extends('layouts/commonMaster')

@section('title', $title ?? 'Login Basic - Pages')

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@endsection

@section('layoutContent')
  <!-- Content -->
  {{ $slot }}
  <!--/ Content -->
@endsection