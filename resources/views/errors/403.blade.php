{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: errors/403.blade.php (MPW-V2 §6.6.2, §7.3, §8.1, §8.2) --}}
@extends('layouts.error')

@section('code', '403')
@section('title', 'Acceso restringido')
@section('message', 'No cuentas con los permisos necesarios para consultar esta sección o realizar esta acción. Si consideras que se trata de un error, comparte el folio de incidente con tu coordinador o con el equipo de soporte.')
@section('accentBar', 'bg-acode-warning')
@section('iconBox', 'bg-acode-warning/15 border border-acode-warning/40 text-acode-primary')
@section('icon', 'lock')
