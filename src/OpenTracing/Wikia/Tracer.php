<?php

namespace OpenTracing\Wikia;

use OpenTracing;
use OpenTracing\Wikia\Recorder\Recorder;
use OpenTracing\Exception\InvalidFormatException;

class Tracer extends OpenTracing\Tracer
{

    protected static $propagators = null;

    private $recorder = null;

    public function __construct(Recorder $recorder = null)
    {
        $this->recorder = $recorder;
    }

    public function startSpan($operationName = null, $parent = null, $tags = null, $startTime = null)
    {
        if (!is_null($parent) && !$parent instanceof Span) {
            throw new \InvalidArgumentException('Unsupported Span object provided');
        }

        $newSpanData = new SpanData();
        $newSpanData->startTime = !is_null($startTime) ? $startTime : microtime(true);

        if (!$parent) {
            $newSpanData->traceId = $this->randomTextualId();
            $newSpanData->spanId = $newSpanData->traceId;
        } else {
            $parentSpanData = $parent->getData();
            $newSpanData->traceId = $parentSpanData->traceId;
            $newSpanData->parentSpanId = $parentSpanData->spanId;
            $newSpanData->spanId = $this->randomTextualId();

            $newSpanData->baggage = $parentSpanData->baggage;
        }

        $newSpanData->operationName = $operationName;
        $newSpanData->startTime = is_null($startTime) ? microtime(true) : $startTime;
        $newSpanData->tags = is_null($tags) ? [] : $tags;

        return new Span($this, $newSpanData);
    }

    protected function randomTextualId() {
        return bin2hex($this->randomId());
    }

    protected function randomId()
    {
        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv(8, MCRYPT_DEV_RANDOM);
        } else {
            if (function_exists('openssl_random_pseudo_bytes')) {
                return openssl_random_pseudo_bytes(8);
            } else {
                $s = '';
                for ($i = 0; $i < 8; $i++) {
                    $s .= chr(mt_rand(0, 255));
                }

                return $s;
            }
        }
    }

    public function injector($format)
    {
        return $this->getPropagator($format);
    }

    public function createSpan($traceId, $spanId, $baggage)
    {
        $spanData = new SpanData();
        $spanData->traceId = $traceId;
        $spanData->spanId = $spanId;
        $spanData->startTime = microtime(true);
        $spanData->baggage = $baggage;

        return new Span($this, $spanData);
    }

    protected function getPropagator($format)
    {
        $this->initPropagators();

        if (empty( self::$propagators[$format] )) {
            throw new InvalidFormatException();
        }

        return self::$propagators[$format];
    }

    protected function initPropagators()
    {
        if (!is_array(self::$propagators)) {
            self::$propagators = [
                Format::SPLIT_TEXT => new Propagator\SplitTextPropagator($this),
                Format::SPLIT_BINARY => new Propagator\SplitBinaryPropagator($this),
                Format::PACKED_HTTP_HEADERS => new Propagator\PackedHttpHeadersPropagator($this),
                Format::RAW_HTTP_HEADERS => new Propagator\RawHttpHeadersPropagator($this),
            ];
        }
    }

    public function extractor($format)
    {
        return $this->getPropagator($format);
    }

    public function flush()
    {

    }

    public function getRecorder() {
        return $this->recorder;
    }
}