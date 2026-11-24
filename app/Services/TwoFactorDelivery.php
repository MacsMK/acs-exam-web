<?php

declare(strict_types=1);

/**
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * Proyecto: Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo: TwoFactorDelivery.php
 * Ubicación: app/Services/TwoFactorDelivery.php
 * Requerimiento: RF-AUT-003, RF-AUT-004, WEB-AUT-001
 * Autor: Codex (implementación local)
 * Versión: 1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción: Distingue la entrega por correo de la demostración local.
 */

namespace App\Services;

use App\Models\User;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class TwoFactorDelivery
{
    private const EXTERNAL_TRANSPORTS = ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'mailgun', 'resend'];

    /**
     * Selecciona el canal permitido para emitir un código a la cuenta.
     *
     * @param User $user Destinatario del desafío.
     * @return string Canal que acreditará la emisión.
     * @throws ValidationException Si el correo de la cuenta no se puede entregar.
     */
    public function channelFor(User $user): string
    {
        if ($this->demoEnabled($user)) {
            return 'demo';
        }

        $mailer = $this->defaultMailer();
        if (app()->environment('testing') && $this->transport($mailer) === 'array') {
            return 'test';
        }

        $recipient = $this->mailerConfiguration($mailer)['to']['address'] ?? config('mail.to.address');
        if (($recipient === null || $recipient === $user->email) && $this->canDeliver($mailer)) {
            return 'mail';
        }

        throw $this->unavailable();
    }

    /**
     * Comprueba el canal previamente acreditado para el código vigente.
     *
     * @param User $user Cuenta con un desafío persistido.
     * @return bool Validez del canal en la instalación actual.
     */
    public function permits(User $user): bool
    {
        return match ($user->two_factor_delivery) {
            'mail' => true,
            'demo' => $this->demoEnabled($user),
            'test' => app()->environment('testing'),
            default => false,
        };
    }

    /**
     * Entrega el código por correo o habilita su presentación en modo demo.
     *
     * @param User $user Destinatario del desafío.
     * @param string $code Código de un solo uso.
     * @param string $channel Canal validado para la emisión.
     * @return void
     * @throws ValidationException Si no se puede entregar el correo.
     */
    public function send(User $user, string $code, string $channel): void
    {
        if ($channel === 'demo') {
            return;
        }

        try {
            $sent = Mail::raw(
                "Tu código de verificación es: {$code}\n\n"
                .'Es válido durante '.AuthService::TWO_FACTOR_TTL_MINUTES." minutos.\n"
                .'Si no solicitaste este código, ignora este mensaje.',
                static function ($message) use ($user): void {
                    $message->to($user->email)->subject('Código de verificación - A-Code Solutions');
                },
            );
        } catch (TransportExceptionInterface) {
            Log::warning('No se pudo entregar un correo de verificación en dos pasos.');

            throw $this->unavailable();
        }

        if ($sent === null && ! app()->environment('testing')) {
            throw $this->unavailable();
        }
    }

    /**
     * Verifica que la demostración esté habilitada en local o para una cuenta sintética en pruebas.
     *
     * @param User $user Cuenta destinataria.
     * @return bool Disponibilidad de la demostración para esta cuenta.
     */
    private function demoEnabled(User $user): bool
    {
        if (! app()->environment(['local', 'testing']) || config('auth.two_factor_demo') !== true) {
            return false;
        }

        $transport = $this->transport($this->defaultMailer());

        return in_array($transport, ['array', 'log'], true)
            && (app()->environment('local')
                || preg_match('/@(?:example\.test|(?:[a-z0-9-]+\.)*acode-solutions\.test)\z/i', $user->email) === 1);
    }

    /**
     * Comprueba la capacidad de entrega externa de todo el transporte seleccionado.
     *
     * @param string $mailer Nombre del transporte configurado.
     * @param list<string> $parents Transportes ya revisados en la cadena actual.
     * @return bool Indica si el transporte y sus alternativas entregan correo.
     */
    private function canDeliver(string $mailer, array $parents = []): bool
    {
        $transport = $this->transport($mailer);
        if (in_array($transport, self::EXTERNAL_TRANSPORTS, true)) {
            return true;
        }
        if (! in_array($transport, ['failover', 'roundrobin'], true) || in_array($mailer, $parents, true)) {
            return false;
        }

        $alternatives = $this->mailerConfiguration($mailer)['mailers'] ?? [];
        if (! is_array($alternatives) || $alternatives === []) {
            return false;
        }

        foreach ($alternatives as $alternative) {
            if (! is_string($alternative) || ! $this->canDeliver($alternative, [...$parents, $mailer])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene el tipo efectivo de un transporte configurado por nombre.
     *
     * @param string $mailer Nombre del transporte configurado.
     * @return string Tipo de transporte o cadena vacía si no existe.
     */
    private function transport(string $mailer): string
    {
        $options = $this->mailerConfiguration($mailer);

        return (string) ($options['transport'] ?? '');
    }

    /**
     * Resuelve la configuración efectiva con el mismo parser de URL que Laravel.
     *
     * @param string $mailer Transporte configurado.
     * @return array<string, mixed> Opciones efectivas del transporte.
     */
    private function mailerConfiguration(string $mailer): array
    {
        $legacyTransport = config('mail.driver');
        $options = $legacyTransport ? config('mail') : config("mail.mailers.{$mailer}");
        if (! is_array($options)) {
            return [];
        }

        if (! isset($options['url'])) {
            $options['transport'] ??= $legacyTransport;

            return $options;
        }

        try {
            $options = (new ConfigurationUrlParser)->parseConfiguration($options);
        } catch (InvalidArgumentException) {
            return [];
        }
        $options['transport'] = $options['driver'] ?? null;

        return $options;
    }

    /**
     * Obtiene el nombre del transporte que usará el correo de autenticación.
     *
     * @return string Nombre del transporte efectivo.
     */
    private function defaultMailer(): string
    {
        return (string) (config('mail.driver') ?? config('mail.default'));
    }

    /**
     * Devuelve un error de entrega que se puede mostrar al usuario.
     *
     * @return ValidationException Error de validación del segundo paso.
     */
    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => ['No se pudo enviar el código de verificación. Contacta al administrador para revisar el servicio de correo.'],
        ]);
    }
}
