<?php
declare(strict_types=1);

namespace WoohooLabsTest\Harmony;

use PHPUnit\Framework\TestCase;
use WoohooLabs\Harmony\Harmony;
use WoohooLabs\Harmony\Tests\Utils\Condition\StubCondition;
use WoohooLabs\Harmony\Tests\Utils\Middleware\ExceptionMiddleware;
use WoohooLabs\Harmony\Tests\Utils\Middleware\FakeMiddleware;
use WoohooLabs\Harmony\Tests\Utils\Middleware\HeaderMiddleware;
use WoohooLabs\Harmony\Tests\Utils\Middleware\InternalServerErrorMiddleware;
use WoohooLabs\Harmony\Tests\Utils\Middleware\SpyMiddleware;
use WoohooLabs\Harmony\Tests\Utils\Psr7\DummyResponse;
use WoohooLabs\Harmony\Tests\Utils\Psr7\DummyServerRequest;

class HarmonyTest extends TestCase
{
    /**
     * @test
     */
    public function construct()
    {
        $harmony = $this->createHarmony();

        $this->assertInstanceOf(DummyServerRequest::class, $harmony->getRequest());
    }

    /**
     * @test
     */
    public function invokeAllMiddleware()
    {
        $harmony = $this->createHarmony();
        $harmony->addMiddleware(new HeaderMiddleware("dummy", "dummy"));
        $harmony->addMiddleware(new FakeMiddleware());
        $harmony->addMiddleware(new InternalServerErrorMiddleware(new DummyResponse()));

        $response = $harmony();

        $this->assertEquals(["dummy"], $response->getHeader("dummy"));
        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function returnAfterSecondMiddleware()
    {
        $harmony = $this->createHarmony();
        $harmony->addMiddleware(new FakeMiddleware());
        $harmony->addMiddleware(new InternalServerErrorMiddleware(new DummyResponse()));
        $harmony->addMiddleware(new ExceptionMiddleware());

        $response = $harmony();

        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function getRequest()
    {
        $harmony = $this->createHarmony();
        $request = new DummyServerRequest();

        $harmony();

        $this->assertEquals($request, $harmony->getRequest());
    }

    /**
     * @test
     */
    public function getResponse()
    {
        $harmony = $this->createHarmony();
        $response = new DummyResponse();

        $result = $harmony();

        $this->assertEquals($response, $result);
    }

    /**
     * @test
     */
    public function addMiddleware()
    {
        $harmony = $this->createHarmony();
        $harmony->addMiddleware(new FakeMiddleware("dummy1"), "dummy1");
        $harmony->addMiddleware(new FakeMiddleware("dummy2"), "dummy2");
        $harmony->addMiddleware(new FakeMiddleware("dummy3"), "dummy3");

        $this->assertInstanceOf(FakeMiddleware::class, $harmony->getMiddleware("dummy1"));
        $this->assertInstanceOf(FakeMiddleware::class, $harmony->getMiddleware("dummy2"));
        $this->assertInstanceOf(FakeMiddleware::class, $harmony->getMiddleware("dummy3"));
    }

    /**
     * @test
     */
    public function getNonExistentMiddleware()
    {
        $harmony = $this->createHarmony();

        $this->assertNull($harmony->getMiddleware("dummy"));
    }

    /**
     * @test
     */
    public function getExistentMiddleware()
    {
        $middleware = new FakeMiddleware();

        $harmony = $this->createHarmony();
        $harmony->addMiddleware($middleware, "dummy");

        $this->assertEquals($middleware, $harmony->getMiddleware("dummy"));
    }

    /**
     * @test
     */
    public function invokeMiddlewareConditionally()
    {
        $middleware = new SpyMiddleware();

        $harmony = $this->createHarmony();
        $harmony->addCondition(
            new StubCondition(true),
            function (Harmony $harmony) use ($middleware) {
                $harmony->addMiddleware($middleware);
            }
        );
        $harmony();

        $this->assertTrue($middleware->isInvoked());
    }

    /**
     * @test
     */
    public function doNotInvokeMiddlewareWhenConditionIsFalse()
    {
        $middleware = new SpyMiddleware();

        $harmony = $this->createHarmony();
        $harmony->addCondition(
            new StubCondition(false),
            function (Harmony $harmony) use ($middleware) {
                $harmony->addMiddleware($middleware);
            }
        );

        $harmony();

        $this->assertFalse($middleware->isInvoked());
    }

    protected function createHarmony()
    {
        return new Harmony(new DummyServerRequest(), new DummyResponse());
    }
}
