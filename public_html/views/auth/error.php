<?php
// /public_html/views/auth/error.php

declare(strict_types=1);
?>
<section class="auth-card card">
    <p class="eyebrow">Single sign-on</p>
    <h1>Sign-in failed</h1>

    <div class="alert">
        <?= e($message) ?>
    </div>

    <div class="actions">
        <a
            class="button"
            href="/auth/login"
        >
            Try again
        </a>
    </div>
</section>
