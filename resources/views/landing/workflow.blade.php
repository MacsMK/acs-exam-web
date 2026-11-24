<section id="como-funciona" class="workflow-section section-space" aria-labelledby="workflow-title">
    <div class="wrap workflow-layout">
        <div>
            <h2 id="workflow-title" class="section-title">Así se aplica un examen</h2>
            <ol class="workflow-steps">
                <li>
                    <h3>Crea tu grupo</h3>
                    <p>Comparte el QR o el folio de ocho caracteres para que tus alumnos se inscriban.</p>
                </li>
                <li>
                    <h3>Prepara el examen</h3>
                    <p>Elige los reactivos de tu banco y configura los tiempos y la fecha de aplicación.</p>
                </li>
                <li>
                    <h3>Sigue la aplicación</h3>
                    <p>Consulta el avance del grupo y las incidencias de cambio de pestaña mientras responden.</p>
                </li>
                <li>
                    <h3>Publica los resultados</h3>
                    <p>Revisa las respuestas abiertas, consulta los aciertos y descarga las calificaciones.</p>
                </li>
            </ol>
        </div>

        <article class="demo-window" aria-labelledby="demo-title">
            <div class="demo-bar">
                <span class="demo-bar-title"><x-icon name="document" /> Evaluación de ejemplo</span>
            </div>
            <form class="demo-body" data-exam-demo>
                <div class="demo-meta">
                    <span>Opción múltiple</span>
                    <span>1 punto</span>
                </div>
                <fieldset class="demo-question" disabled>
                    <legend id="demo-title">¿Qué estructura repite un bloque de código mientras se cumple una condición?</legend>
                    @foreach (['variable' => 'Una variable', 'bucle' => 'Un bucle', 'funcion' => 'Una función'] as $value => $answer)
                        <label class="demo-option">
                            <input type="radio" name="demo-answer" value="{{ $value }}" required>
                            <span class="demo-letter" aria-hidden="true">{{ chr(65 + $loop->index) }}.</span>
                            <span>{{ $answer }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <p class="demo-feedback" role="status" aria-live="polite" aria-atomic="true"></p>
                <button class="button button--primary demo-submit" type="submit" hidden>Comprobar respuesta</button>
                <details class="demo-solution">
                    <summary>Consultar respuesta</summary>
                    <p>La respuesta correcta es «Un bucle». Ejecuta de nuevo el bloque mientras la condición sea verdadera.</p>
                </details>
            </form>
        </article>
    </div>
</section>
