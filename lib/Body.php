<?php

namespace Aerys;

use Amp\PromiseStream;
use Amp\Promise;

/**
 * An API allowing responders to buffer or stream request entity bodies
 *
 * Applications are invoked as soon as headers are received and before
 * entity body data is parsed. The $request->body instance allows
 * applications to await receipt of the full body (buffer) or stream
 * it in chunks as it arrives.
 *
 * Buffering Example:
 *
 *     $responder = function(Request $request, Response $response) {
 *          $bufferedBody = yield $request->getBody();
 *          $response->send("Echoing back the request body: {$bufferedBody}");
 *     };
 *
 * Streaming Example:
 *
 *     $responder = function(Request $request, Response $response) {
 *          $body = "";
 *          foreach ($request->getBody()->stream() as $bodyPart) {
 *              $body .= yield $bodyPart;
 *          }
 *          $response->send("Echoing back the request body: {$body}");
 *     };
 */
class Body extends PromiseStream implements Promise {
    private $whens = [];
    private $watchers = [];
    private $string;

    public function __construct(Promise $promise) {
        $promise->watch(function($data) {
            foreach ($this->watchers as list($func, $cbData)) {
                $func($data, $cbData);
            }
        });
        parent::__construct($promise);
        $when = function ($e, $bool) use (&$continue) {
            $continue = $bool;
        };
        $promise->when(function() use (&$continue, $when) {
            $this->valid()->when($when);
            while ($continue) {
                $string[] = $this->consume();
                $this->valid()->when($when);
            }

            if (isset($string)) {
                if (isset($string[1])) {
                    $string = implode($string);
                } else {
                    $string = $string[0];
                }
            } else {
                $string = "";
            }
            $this->string = $string;

            foreach ($this->whens as list($when, $data)) {
                $when(null, $string, $data);
            }
            $this->whens = $this->watchers = [];
        });
    }

    public function when(callable $func, $data = null) {
        if (isset($this->string)) {
            $func(null, $this->string, $data);
        } else {
            $this->whens[] = [$func, $data];
        }
        return $this;
    }

    public function watch(callable $func, $data = null) {
        if (!isset($this->string)) {
            $this->watchers[] = [$func, $data];
        }
        return $this;
    }
}
