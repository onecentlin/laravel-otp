<?php

/*
 * @copyright 2018 Hilmi Erdem KEREN
 * @license MIT
 */

namespace Erdemkeren\TemporaryAccess\Http\Controllers;

use Mockery as M;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Facade;
use Erdemkeren\TemporaryAccess\TokenInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Validator;
use Erdemkeren\TemporaryAccess\TemporaryAccessService;

if (! \function_exists('\Erdemkeren\TemporaryAccess\Http\Controllers\session')) {
    function session($a = null, $b = null)
    {
        global $testerClass;

        return $testerClass::$functions->session($a, $b);
    }
}

if (! \function_exists('\Erdemkeren\TemporaryAccess\Http\Controllers\cookie')) {
    function cookie()
    {
        global $testerClass;

        return $testerClass::$functions->cookie();
    }
}

if (! \function_exists('\Erdemkeren\TemporaryAccess\Http\Controllers\view')) {
    function view($a)
    {
        global $testerClass;

        return $testerClass::$functions->view($a);
    }
}

if (! \function_exists('\Erdemkeren\TemporaryAccess\Http\Controllers\redirect')) {
    function redirect()
    {
        global $testerClass;

        return $testerClass::$functions->redirect();
    }
}

/**
 * @coversNothing
 */
class OtpControllerTest extends TestCase
{
    public static $functions;

    private $validator;

    private $service;

    public function setUp()
    {
        global $testerClass;
        $testerClass = self::class;
        $this::$functions = M::mock();

        $this->validator = M::mock(Validator::class);
        $this->service = M::mock(TemporaryAccessService::class);

        $app = new Container();
        $app->singleton('app', 'Illuminate\Container\Container');
        // $app->singleton('config', 'Illuminate\Config\Repository');

        $app->bind('validator', function ($app) {
            return $this->validator;
        });

        $app->singleton('temporary-access', function ($app) {
            return $this->service;
        });

        Facade::setFacadeApplication($app);
    }

    public function tearDown()
    {
        M::close();

        global $testerClass;
        $testerClass = null;

        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    public function testCreate()
    {
        $controller = new OtpController();

        $request = M::mock(Request::class);

        $this::$functions->shouldReceive('session')
            ->once()->with('otp_requested', false)
            ->andReturn(true);

        $this::$functions->shouldReceive('view')
            ->once()->with('otp.create')
            ->andReturn('view');

        $this->assertSame('view', $controller->create($request));
    }

    public function testCreateRedirectsWhenNotRedirectedByMiddleware()
    {
        $controller = new OtpController();

        $request = M::mock(Request::class);

        $this::$functions->shouldReceive('session')
            ->once()->with('otp_requested', false)
            ->andReturn(false);

        $this::$functions->shouldReceive('redirect')
            ->once()
            ->andReturn($response = M::mock(RedirectResponse::class));

        $this->assertSame($response, $controller->create($request));
    }

    public function testStore()
    {
        $controller = new OtpController();

        $request = M::mock(Request::class);

        $this::$functions->shouldReceive('session')
            ->once()->with('otp_requested', false)
            ->andReturn(true);

        $request->shouldReceive('all')
            ->once()
            ->andReturn([
                'password' => $password = '12345',
            ]);

        $this->validator->shouldReceive('make')
            ->once()->with([
                'password' => $password,
            ], [
                'password' => 'required|string',
            ])->andReturn($this->validator);

        $this->validator->shouldReceive('fails')
            ->once()
            ->andReturn(false);

        $request->shouldReceive('user')
            ->once()
            ->andReturn($authenticable = M::mock(Authenticatable::class));

        $request->shouldReceive('input')
            ->once()->with('password')
            ->andReturn($password);

        $this->service->shouldReceive('retrieveByPlainText')
            ->once()->with($authenticable, $password)
            ->andReturn($token = M::mock(TokenInterface::class));

        $token->ShouldReceive('expired')
            ->once()
            ->andReturn(false);

        $this::$functions->shouldReceive('session')
            ->twice()->with(null, null)
            ->andReturn(new class(self::$functions) {
                private $funcs;

                public function __construct($funcs)
                {
                    $this->funcs = $funcs;
                }

                public function forget($arg)
                {
                    $this->funcs->forget($arg);
                }

                public function pull($arg)
                {
                    return $this->funcs->pull($arg);
                }
            });

        $this::$functions->shouldReceive('forget')
            ->once()->with('otp_requested')
            ->andReturn(true);

        $this::$functions->shouldReceive('redirect')
            ->once()
            ->andReturn($c = new class(self::$functions) {
                private $funcs;

                public function __construct($funcs)
                {
                    $this->funcs = $funcs;
                }

                public function to($arg)
                {
                    return $this->funcs->to($arg);
                }

                public function withCookie($arg)
                {
                    return $this->funcs->withCookie($arg);
                }
            });

        $this::$functions->shouldReceive('pull')
            ->once()
            ->with('otp_redirect_url')
            ->andReturn($otpRedirectUrl = 'url');

        $this::$functions->shouldReceive('to')
            ->once()
            ->with($otpRedirectUrl)
            ->andReturn($c);

        $token->ShouldReceive('expiryTime')
            ->once()
            ->andReturn(60);

        $this::$functions->shouldReceive('withCookie')
            ->once()
            ->with('foo')
            ->andReturn($response = M::mock(RedirectResponse::class));

        $token->shouldReceive('__toString')->once()->andReturn($password);

        $this::$functions->shouldReceive('make')
            ->once()
            ->with('otp_token', $password, 1)
            ->andReturn('foo');

        $this::$functions->shouldReceive('cookie')
            ->once()
            ->andReturn(new class($this::$functions) {
                private $funcs;

                public function __construct($funcs)
                {
                    $this->funcs = $funcs;
                }

                public function make($n, $v, $t)
                {
                    return $this->funcs->make($n, $v, $t);
                }
            });

        $this->assertSame($response, $controller->store($request));
    }

    public function testStoreRedirectsWhenNotRedirectedByMiddleware()
    {
        $controller = new OtpController();

        $request = M::mock(Request::class);

        $this::$functions->shouldReceive('session')
            ->once()->with('otp_requested', false)
            ->andReturn(false);

        $this::$functions->shouldReceive('redirect')
            ->once()
            ->andReturn($response = M::mock(RedirectResponse::class));

        $this->assertSame($response, $controller->store($request));
    }
}
