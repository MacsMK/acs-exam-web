{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: errors/404.blade.php (MPW-V3 §6.6.2, §7.3, §8.1, §8.2) --}}
@extends('layouts.error')

@section('code', '404')
@section('title', 'Página no encontrada')
@section('message', 'No pudimos localizar la página o el recurso que estás buscando. Es posible que el enlace haya cambiado, expirado o contenga un error de escritura.')
@section('accentBar', 'bg-acode-tech')
@section('iconBox', 'bg-acode-tech/10 border border-acode-tech/25 text-acode-tech')
@section('icon', 'search')
