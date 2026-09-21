<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_del_sitio_redirige_al_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/app/login.html');
    }
}
