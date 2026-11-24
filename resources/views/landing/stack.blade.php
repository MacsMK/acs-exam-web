<div class="exam-stack">
    <svg class="stack-original" width="1024" height="1024" viewBox="0 0 1024 1024" role="img" aria-labelledby="stack-title" focusable="false">
        <title id="stack-title">Una hoja de respuestas, una capa de supervisión y una base de resultados en vista isométrica.</title>
        <defs>
            {{-- The outlines follow the 1024 × 1024 illustration. The two rear surfaces extend behind the piece above. --}}
            <clipPath id="stack-paper-outline" clipPathUnits="objectBoundingBox">
                <path id="stack-paper-shape" transform="scale(.0009765625)" d="M53 300 Q53 297 57 295 L507 45 Q510 43 514 45 C554 68 602 97 640 113 C665 125 702 138 725 144 Q729 145 732 148 L861 246 Q870 252 880 258 L958 302 Q960 303 960 306 L959 329 Q959 331 956 333 L883 380 817 420 750 460 666 510 598 550 515 599 Q511 601 506 598 L472 578 424 550 355 510 270 460 201 418 138 380 56 330 Q53 328 53 325 Z"/>
            </clipPath>
            <clipPath id="stack-glass-outline" clipPathUnits="objectBoundingBox">
                <path id="stack-glass-shape" transform="scale(.0009765625)" d="M75 485 L505 234 Q511 231 517 235 L940 485 Q947 489 947 494 L946 519 Q945 524 941 526 L521 778 Q512 783 504 779 L77 528 Q71 525 71 521 L70 495 Q69 490 75 485 Z"/>
            </clipPath>
            <clipPath id="stack-glass-visible" clipPathUnits="objectBoundingBox">
                <path transform="scale(.0009765625)" d="M0 295 L137 381 201 419 270 461 355 511 424 551 472 579 506 600 Q511 603 516 601 L598 552 666 512 750 462 817 422 884 382 1024 290 V1024 H0 Z"/>
            </clipPath>
            <clipPath id="stack-base-outline" clipPathUnits="objectBoundingBox">
                <path id="stack-base-shape" transform="scale(.0009765625)" d="M72 625 L495 380 Q510 372 527 382 L942 625 Q954 631 954 643 L952 716 Q952 726 943 732 L532 984 Q512 995 492 986 L74 733 Q64 727 64 716 L63 644 Q63 633 72 625 Z"/>
            </clipPath>
            <clipPath id="stack-base-visible" clipPathUnits="objectBoundingBox">
                <path transform="scale(.0009765625)" d="M0 483 L77 530 150 573 200 601 250 631 350 689 450 749 499 777 Q512 786 522 779 L600 731 700 671 800 611 900 551 941 527 1024 478 V1024 H0 Z"/>
            </clipPath>
            <clipPath id="stack-silhouette" clipPathUnits="objectBoundingBox">
                <use href="#stack-base-shape"/>
                <use href="#stack-glass-shape"/>
                <use href="#stack-paper-shape"/>
            </clipPath>
        </defs>
        <image href="{{ asset('images/landing/evaluation-stack.webp') }}" width="1024" height="1024" clip-path="url(#stack-silhouette)"/>
    </svg>
    <div class="stack-pieces" aria-hidden="true" hidden>
        <span class="stack-piece stack-base"></span>
        <span class="stack-piece stack-glass"></span>
        <span class="stack-piece stack-paper"></span>
    </div>
</div>
