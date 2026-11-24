<section id="movil" class="student-section section-space" aria-labelledby="student-title">
    <div class="wrap student-layout">
        <div class="student-copy">
            <h2 id="student-title" class="section-title">Tu aula, desde el celular.</h2>
            <p class="intro">Únete al grupo con el QR o el folio de tu docente. En la app móvil puedes presentar exámenes, recibir avisos y consultar tus notas.</p>
            <a class="button button--light" href="{{ route('auth.register.student.show') }}">
                Registrarme como alumno en la web
            </a>
            <p class="student-footnote">El acceso es gratuito para estudiantes.</p>
        </div>
        <figure class="student-preview">
            <div class="student-phone">
                <div class="student-screen">
                    <div class="student-phone-bar">
                        <span class="student-phone-speaker" aria-hidden="true"></span>
                        <div><span>Mi aula</span><x-icon name="userCircle" /></div>
                    </div>
                    <div class="student-phone-content">
                        <div class="student-group">
                            <div>
                                <span class="student-phone-label">Mi grupo</span>
                                <strong>Programación web</strong>
                                <small>Folio · WEB2026A</small>
                            </div>
                            <x-icon name="qrCode" />
                        </div>
                        <div class="student-activity">
                            <span class="student-phone-label">Próximo examen</span>
                            <strong>Fundamentos de programación</strong>
                            <span class="student-schedule"><x-icon name="clock" /> Hoy, 10:00 h</span>
                        </div>
                        <div class="student-result">
                            <div><span class="student-phone-label">Última calificación</span><strong>Lógica de algoritmos</strong></div>
                            <span class="student-score">9.5<small>/ 10</small></span>
                        </div>
                    </div>
                    <div class="student-home-indicator" aria-hidden="true"></div>
                </div>
            </div>
            <figcaption>Vista de ejemplo en móvil</figcaption>
        </figure>
    </div>
</section>
