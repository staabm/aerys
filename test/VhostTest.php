<?php

namespace Aerys\Test;

use Aerys\Client;
use Aerys\InternalRequest;
use Aerys\Vhost;
use Aerys\VhostContainer;

class VhostTest extends \PHPUnit_Framework_TestCase {
    function testVhostSelection() {
        $vhosts = new VhostContainer($this->getMock('Aerys\HttpDriver'));
        $localvhost = new Vhost("localhost", [["127.0.0.1", 80], ["::", 8080]], function(){}, [function(){yield;}]);
        $vhosts->use($localvhost);
        $vhost = new Vhost("", [["127.0.0.1", 80], ["::", 80]], function(){}, [function(){yield;}]);
        $vhosts->use($vhost);

        $this->assertEquals(2, $vhosts->count());

        $ireq = new InternalRequest;
        $ireq->client = new Client;
        $ireq->headers["host"][0] = "localhost";
        $this->assertEquals($localvhost, $vhosts->selectHost($ireq));
        $ireq->headers["host"][0] = "[::]:80";
        $this->assertEquals($vhost, $vhosts->selectHost($ireq));

        $ireq->uriRaw = "http://localhost/";
        $ireq->uriPort = 80;
        $ireq->uriHost = "localhost";
        $this->assertEquals($localvhost, $vhosts->selectHost($ireq));
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot register encrypted host `localhost`; unencrypted host `*` registered on conflicting port `127.0.0.1:80`
     */
    function testCryptoResolutionFailure() {
        $vhosts = new VhostContainer($this->getMock('Aerys\HttpDriver'));
        $vhost = new Vhost("", [["127.0.0.1", 80], ["::", 80]], function(){}, [function(){yield;}]);
        $vhosts->use($vhost);
        $vhost = new Vhost("localhost", [["127.0.0.1", 80]], function(){}, [function(){yield;}]);
        $vhost->setCrypto(["local_cert" => __DIR__."/server.pem"]);
        $vhosts->use($vhost);
    }

    function testCryptoVhost() {
        $vhosts = new VhostContainer($this->getMock('Aerys\HttpDriver'));
        $vhost = new Vhost("", [["127.0.0.1", 80], ["::", 80]], function(){}, []);
        $vhosts->use($vhost);
        $vhost = new Vhost("localhost", [["127.0.0.1", 80]], function(){}, []);
        $vhosts->use($vhost);
        $vhost = new Vhost("localhost", [["127.0.0.1", 8080]], function(){}, []);
        $vhost->setCrypto(["local_cert" => __DIR__."/server.pem"]);
        $vhosts->use($vhost);

        $this->assertTrue(isset($vhosts->getTlsBindingsByAddress()["tcp://127.0.0.1:8080"]));
        $this->assertEquals(["tcp://127.0.0.1:80", "tcp://[::]:80", "tcp://127.0.0.1:8080"], array_values($vhosts->getBindableAddresses()));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage At least one interface must be passed, an empty interfaces array is not allowed
     */
    function testNoInterfaces() {
        new Vhost("", [], function() {}, []);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid host port: 0; integer in the range [1-65535] required
     */
    function testBadPort() {
        new Vhost("", [["127.0.0.1", 0]], function() {}, []);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage IPv4 or IPv6 address required: rd.lo.wr.ey
     */
    function testBadInterface() {
        new Vhost("", [["rd.lo.wr.ey", 1025]], function() {}, []);
    }
}