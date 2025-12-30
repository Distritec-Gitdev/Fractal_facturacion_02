@extends('layouts.app')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/gracias.css') }}">


@section('content')
<div class="container" style="max-width: 600px; margin: 80px auto;">
  <div class="container py-5">
    <h2>¡Gracias! Hemos procesado tu información</h2>

    @if(!empty($email_ok))
      <div class="alert alert-success mt-3">{{ $email_ok }}</div>
    @endif

    @if(!empty($email_error))
      <div class="alert alert-warning mt-3">{{ $email_error }}</div>
    @endif

    <p class="mt-4">Si tienes dudas, contáctanos.</p>
  </div>
</div>
@endsection
