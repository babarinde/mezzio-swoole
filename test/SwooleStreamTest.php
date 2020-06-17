<?php

/**
 * @see       https://github.com/mezzio/mezzio-swoole for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-swoole/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Swoole;

use Mezzio\Swoole\SwooleStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Swoole\Http\Request as SwooleHttpRequest;

use function extension_loaded;
use function strlen;
use function substr;

use const SEEK_CUR;
use const SEEK_END;

class SwooleStreamTest extends TestCase
{
    const DEFAULT_CONTENT = 'This is a test!';

    protected function setUp() : void
    {
        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('The Swoole extension is not available');
        }
        $this->request = $this->prophesize(SwooleHttpRequest::class);
        $this->request
            ->rawContent()
            ->willReturn(self::DEFAULT_CONTENT);

        $this->stream = new SwooleStream($this->request->reveal());
    }

    public function testEofWhenOneCharacterLeftCase(): void
    {
        $len = strlen(self::DEFAULT_CONTENT);

        $this->assertEquals('This is a test', $this->stream->read($len - 1));
        $this->assertFalse($this->stream->eof());

        $this->assertEquals('!', $this->stream->read(1));
        $this->assertTrue($this->stream->eof());
    }

    public function testGetContentsWithNoRawContent()
    {
        $request = $this->prophesize(SwooleHttpRequest::class);
        $request
            ->rawContent()
            ->willReturn(false);

        $stream = new SwooleStream($request->reveal());

        $this->assertEquals('', $stream->getContents());
    }

    public function testStreamIsAPsr7StreamInterface()
    {
        $this->assertInstanceOf(StreamInterface::class, $this->stream);
    }

    public function testGetContentsWhenIndexIsAtStartOfContentReturnsFullContents()
    {
        $this->assertEquals(self::DEFAULT_CONTENT, $this->stream->getContents());
    }

    public function testGetContentsReturnsOnlyFromIndexForward()
    {
        $index = 10;
        $this->stream->seek($index);
        $this->assertEquals(substr(self::DEFAULT_CONTENT, $index), $this->stream->getContents());
    }

    public function testGetContentsWithEmptyBodyReturnsEmptyString()
    {
        $this->request
            ->rawContent()
            ->willReturn('');
        $this->stream = new SwooleStream($this->request->reveal());

        $this->assertEquals('', $this->stream->getContents());
    }

    public function testToStringReturnsFullContents()
    {
        $this->assertEquals(self::DEFAULT_CONTENT, (string) $this->stream);
    }

    public function testToStringReturnsAllContentsEvenWhenIndexIsNotAtStart()
    {
        $this->stream->seek(10);
        $this->assertEquals(self::DEFAULT_CONTENT, (string) $this->stream);
    }

    public function testGetSizeReturnsRawContentSize()
    {
        $this->assertEquals(
            strlen(self::DEFAULT_CONTENT),
            $this->stream->getSize()
        );
    }

    public function testGetSizeWithEmptyBodyReturnsZero()
    {
        $this->request
            ->rawContent()
            ->willReturn('');
        $this->stream = new SwooleStream($this->request->reveal());

        $this->assertEquals(0, $this->stream->getSize());
    }

    public function testTellIndicatesIndexInString()
    {
        $tot = strlen(self::DEFAULT_CONTENT);
        for ($i = 0; $i < strlen(self::DEFAULT_CONTENT); $i++) {
            $this->stream->seek($i);
            $this->assertEquals($i, $this->stream->tell());
        }
    }

    public function testIsReadableReturnsTrue()
    {
        $this->assertTrue($this->stream->isReadable());
    }

    public function testReadReturnsStringWithGivenLengthAndResetsIndex()
    {
        $result = $this->stream->read(4);
        $this->assertEquals(substr(self::DEFAULT_CONTENT, 0, 4), $result);
        $this->assertEquals(4, $this->stream->tell());
    }

    public function testReadReturnsSubstringFromCurrentIndex()
    {
        $this->stream->seek(4);
        $result = $this->stream->read(4);
        $this->assertEquals(substr(self::DEFAULT_CONTENT, 4, 4), $result);
        $this->assertEquals(8, $this->stream->tell());
    }

    public function testIsSeekableReturnsTrue()
    {
        $this->assertTrue($this->stream->isSeekable());
    }

    public function testSeekUpdatesIndexPosition()
    {
        $this->stream->seek(4);
        $this->assertEquals(4, $this->stream->tell());
        $this->stream->seek(1, SEEK_CUR);
        $this->assertEquals(5, $this->stream->tell());
        $this->stream->seek(-1, SEEK_END);
        $this->assertEquals(strlen(self::DEFAULT_CONTENT) - 1, $this->stream->tell());
    }

    public function testSeekSetRaisesExceptionIfPositionOverflows()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Offset cannot be longer than content size');
        $this->stream->seek(strlen(self::DEFAULT_CONTENT));
    }

    public function testSeekCurRaisesExceptionIfPositionOverflows()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Offset + current position cannot be longer than content size when using SEEK_CUR'
        );
        $this->stream->seek(strlen(self::DEFAULT_CONTENT), SEEK_CUR);
    }

    public function testSeekEndRaisesExceptionIfPOsitionOverflows()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Offset must be a negative number to be under the content size when using SEEK_END'
        );
        $this->stream->seek(1, SEEK_END);
    }

    public function testRewindResetsPositionToZero()
    {
        $this->stream->rewind();
        $this->assertEquals(0, $this->stream->tell());
    }

    public function testIsWritableReturnsFalse()
    {
        $this->assertFalse($this->stream->isWritable());
    }

    public function testWriteRaisesException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream is not writable');
        $this->stream->write('Hello!');
    }

    public function testGetMetadataWithNoArgumentsReturnsEmptyArray()
    {
        $this->assertEquals([], $this->stream->getMetadata());
    }

    public function testGetMetadataWithStringArgumentReturnsNull()
    {
        $this->assertNull($this->stream->getMetadata('foo'));
    }

    public function testDetachReturnsRequestInstance()
    {
        $this->assertSame($this->request->reveal(), $this->stream->detach());
    }

    public function testCloseReturnsNull()
    {
        $this->assertNull($this->stream->close());
    }
}
