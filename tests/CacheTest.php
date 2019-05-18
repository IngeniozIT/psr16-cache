<?php
declare(strict_types = 1);

namespace IngeniozIT\Psr16\Tests;

use PHPUnit\Framework\TestCase;

use IngeniozIT\Psr16\Cache;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * @coversDefaultClass \IngeniozIT\Psr16\Cache;
 */
class CacheTest extends TestCase
{
    protected static $tmpDir;

    public static function setUpBeforeClass(): void
    {
        self::$tmpDir = dirname(__FILE__).'/cacheTest';
    }

    public function setUp(): void
    {
        if (!is_dir(self::$tmpDir)) {
            mkdir(self::$tmpDir);
        }
    }

    public function tearDown(): void
    {
        $it = new \RecursiveDirectoryIterator(self::$tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator(
            $it,
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir(self::$tmpDir);
    }

    /**
     * @return CacheInterface
     */
    public function getCache(): CacheInterface
    {
        return new Cache(self::$tmpDir);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(CacheInterface::class, $this->getCache());
    }

    public function testGenericItem()
    {
        $cache = $this->getCache();
        $cache->set('foo', 'bar');
        $this->assertTrue($cache->has('foo'));
        $this->assertEquals($cache->get('foo'), 'bar');
        $cache->delete('foo');
        $this->assertFalse($cache->has('foo'));
        $this->assertNull($cache->get('foo'));
    }

    public function testMultipleGenericItems()
    {
        $cache = $this->getCache();
        $cache->setMultiple(
            [
            'foo' => 42,
            'bar' => 84.84,
            'baz' => 'test',
            'foo2' => 'that is a foo',
            'bar2' => 'that is a bar'
            ]
        );
        $this->assertEquals(
            [
            'foo' => 42,
            'bar' => 84.84,
            'baz' => 'test',
            'bwork' => null
            ], $cache->getMultiple(
                [
                'foo', 'bar', 'baz', 'bwork'
                ]
            )
        );
        $cache->deleteMultiple(['foo', 'bar']);
        $this->assertEquals(
            [
            'foo' => null,
            'bar' => null,
            'baz' => 'test',
            'bwork' => null
            ], $cache->getMultiple(
                [
                'foo', 'bar', 'baz', 'bwork'
                ]
            )
        );
        $cache->clear();
        $this->assertEquals(
            [
            'foo' => null,
            'bar' => null,
            'baz' => null,
            'bwork' => null,
            'foo2' => null,
            'bar2' => null
            ], $cache->getMultiple(
                [
                'foo', 'bar', 'baz', 'bwork', 'foo2', 'bar2'
                ]
            )
        );
    }

    public function testGetWithIllegalKey()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->get('bad/key');
    }

    public function testSetWithIllegalKey()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->set('bad/key', 'foo');
    }

    public function testDeleteWithIllegalKey()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->delete('bad/key');
    }

    public function testConstructorWithBadPath()
    {
        $this->expectException(InvalidArgumentException::class);
        new Cache('definitelyNotAValidDirectory');
    }

    public function testConstructorWeirdPaths()
    {
        $cache1 = $this->getCache();
        $cache2 = new Cache(dirname(__FILE__).'/cacheTest/');
        $cache3 = new Cache(dirname(__FILE__).'////cacheTest///////');

        $cache1->set('foo', 'bar');
        $this->assertEquals('bar', $cache2->get('foo'));
        $this->assertEquals('bar', $cache3->get('foo'));
    }

    public function testGetWithDefaultValue()
    {
        $cache = $this->getCache();
        $this->assertEquals($cache->get('foo'), null);
        $this->assertEquals($cache->get('foo', 42.42), 42.42);
    }

    public function testSetWithTtl()
    {
        $cache = $this->getCache();
        $cache->set('foo', 'bar', 3);
        $this->assertEquals($cache->get('foo'), 'bar');
        sleep(5);
        $this->assertEquals($cache->get('foo'), null);
    }

    public function testSetWithDateIntervalTtl()
    {
        $cache = $this->getCache();
        $cache->set('foo', 'bar', new \DateInterval('PT3S'));
        $this->assertEquals($cache->get('foo'), 'bar');
        sleep(5);
        $this->assertEquals($cache->get('foo'), null);
    }

    public function testSetWithBadTtl()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->set('foo', 'bar', 'baz');
    }

    public function testSetWithZeroTtl()
    {
        $cache = $this->getCache();
        $cache->set('foo', 'bar', 0);
        $this->assertNull($cache->get('foo'));
        $cache->set('foo', 'bar', -1);
        $this->assertNull($cache->get('foo'));
    }

    public function testSetWithZeroTtlAndExistingCache()
    {
        $cache = $this->getCache();
        $cache->set('foo', 'bar');
        $cache->set('foo', 'bar', 0);
        $this->assertNull($cache->get('foo'));
    }

    public function testGetMultipleWithBadKeys()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple(42);
    }

    public function testSetMultipleWithBadKeys()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple(42);
    }

    public function testDeleteMultipleWithBadKeys()
    {
        $cache = $this->getCache();
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteMultiple(42);
    }
}
