{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: errors/500.blade.php (MPW-V2 §6.6.2, §7.3, §8.1, §8.2) --}}
@extends('layouts.error')

@section('code', '500')
@section('title', 'Error interno del servidor')
@section('message', 'Ocurrió un inconveniente inesperado al procesar tu solicitud. Intenta nuevamente en unos momentos; si el problema persiste, comparte el folio de incidente con el equipo de soporte técnico.')
@section('accentBar', 'bg-acode-danger')
@section('iconBox', 'bg-acode-danger/10 border border-acode-danger/25 text-acode-danger')
@section('icon', 'warning')
