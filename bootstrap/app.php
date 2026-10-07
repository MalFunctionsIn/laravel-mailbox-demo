<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render, Railway, Fly and every other PaaS terminate TLS at their edge
        // and forward plain HTTP to the container. Without trusting the
        // X-Forwarded-Proto header, Laravel believes the request is insecure
        // and generates http:// URLs onto an https:// page — which browsers
        // block as mixed content. The symptom is brutal to diagnose from the
        // outside: forms silently never submit and the dashboard's stylesheet
        // and script never load, so the page renders but nothing works.
        //
        // "*" is the right setting here because the platform owns the edge and
        // the container is not reachable except through it.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
