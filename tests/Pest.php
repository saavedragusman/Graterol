<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso de prueba
|--------------------------------------------------------------------------
|
| Las pruebas de Feature se vinculan a Tests\TestCase (arranca la aplicación).
| Las de Architecture se vinculan al TestCase de PHPUnit porque solo leen
| archivos fuente y no necesitan la aplicación levantada.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->extend(PHPUnit\Framework\TestCase::class)
    ->in('Architecture');
