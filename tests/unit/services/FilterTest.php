<?php

declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use DateTimeImmutable;
use Elabftw\Exceptions\ImproperActionException;

use function str_repeat;
use function hash;
use function uniqid;

class FilterTest extends \PHPUnit\Framework\TestCase
{
    public function testFormatLocalDate(): void
    {
        $input = '2024-10-16 17:12:47';
        $expected = array(
            'date' => '2024-10-16',
            'time' => '17:12:47',
        );
        $this->assertEquals($expected, Filter::separateDateAndTime($input));

        $input = '2024-10-16';
        $expected = array(
            'date' => '2024-10-16',
            'time' => '',
        );
        $this->assertEquals($expected, Filter::separateDateAndTime($input));

        $input = '';
        $expected = array(
            'date' => '',
            'time' => '',
        );
        $this->assertEquals($expected, Filter::separateDateAndTime($input));
        $this->assertSame('Monday, July 14, 2025', Filter::formatLocalDate(new DateTimeImmutable('2025-07-14')));
    }

    public function testTitle(): void
    {
        $this->assertEquals('My super title', Filter::title('My super title'));
        $this->assertEquals('Yep Yop Yip Yup', Filter::title("Yep\r\nYop\nYip\rYup"));
        $this->assertEquals('Untitled', Filter::title(''));
        $this->assertEquals('Untitled', Filter::title(' '));
        $this->assertEquals('no whitespace around', Filter::title(' no whitespace around '));
        // test a too long string
        $this->assertEquals(str_repeat('A', 255), Filter::title(str_repeat('A', 260)));
    }

    public function testBody(): void
    {
        $this->assertEquals('my body', Filter::body('my body'));
        $this->assertEquals('my body', Filter::body('my body<script></script>'));
        $this->expectException(ImproperActionException::class);
        Filter::body(str_repeat('a', 4120001));
    }

    public function testBodyMarkdown(): void
    {
        $this->assertSame('H & \\mathbf{1}', Filter::bodyMarkdown('H & \\mathbf{1}'));
        $this->expectException(ImproperActionException::class);
        Filter::bodyMarkdown(str_repeat('a', 4120001));
    }

    public function testBodyAllowsInternalLinksToOpenInNewWindow(): void
    {
        $link = '<a href="/experiments/1" target="_blank" rel="noreferrer noopener">Experiment</a>';
        $this->assertSame($link, Filter::body($link));

        $this->assertSame(
            '<a href="/experiments/1">Experiment</a>',
            Filter::body('<a href="/experiments/1" target="_top">Experiment</a>'),
        );
    }

    public function testForFilesystem(): void
    {
        $this->assertEquals('blah', Filter::forFilesystem('=blah/'));
        $this->assertEquals('.pdf', Filter::forFilesystem("=bl사회과학원 어 학연구소찦차를 타고 온 펲시맨과 쑛다리 똠방각하η†ah/'\n.pdf"));
        $this->assertEquals('23MJ.gif_th.jpg', Filter::forFilesystem('|23MJ.gif_th.jpg'));
    }

    public function testHexits(): void
    {
        // we use uniqid here so it changes every time
        $input = hash('sha512', uniqid('', true));
        $this->assertEquals($input, Filter::hexits($input));
        $this->assertEquals('abc', Filter::hexits('zzzazzzbzzzczzz'));
        $this->assertEmpty(Filter::hexits('zzzzz'));
    }

    public function testToPureString(): void
    {
        $this->assertEquals('Roger', Filter::toPureString('<a href="attacker.com">Roger</a>'));
        $this->assertEquals('Roger', Filter::toPureString('<script>alert(1)</script><strong>Roger</strong>'));
        $this->assertEquals('Rabbit', Filter::toPureString('<i onwheel=alert(224)>Rabbit</i>'));
    }

    public function testIntOrNull(): void
    {
        $this->assertNull(Filter::intOrNull(''));
        $this->assertSame(42, Filter::intOrNull('42'));
    }

    public function testBodyPreservesTinyMceAccordionClass(): void
    {
        $input = '<details class="mce-accordion"><summary>Summary</summary><p>One</p><p>Two</p></details>';
        $this->assertSame($input, Filter::body($input));
    }

    public function testBodyAllowsInteractiveFormControls(): void
    {
        // select with a selected option
        $this->assertSame(
            '<select name="protocol"><option value="a">A</option><option value="b" selected>B</option></select>',
            Filter::body('<select name="protocol"><option value="a">A</option><option value="b" selected>B</option></select>'),
        );
        // radio buttons, one checked
        $this->assertSame(
            '<input type="radio" name="choice" value="1" checked><input type="radio" name="choice" value="2">',
            Filter::body('<input type="radio" name="choice" value="1" checked><input type="radio" name="choice" value="2">'),
        );
        // checkbox
        $this->assertSame(
            '<input type="checkbox" name="done" value="yes" checked>',
            Filter::body('<input type="checkbox" name="done" value="yes" checked>'),
        );
        // text input and label
        $this->assertSame(
            '<label><input type="text" name="notes" value="hello"> notes</label>',
            Filter::body('<label><input type="text" name="notes" value="hello"> notes</label>'),
        );
        // button with data-action and data-script attributes
        $this->assertSame(
            '<button type="button" data-action="run-script" data-script="analyze.py">Run</button>',
            Filter::body('<button type="button" data-action="run-script" data-script="analyze.py">Run</button>'),
        );
    }

    public function testBodyStripsDangerousAttributesAndElements(): void
    {
        // event handler attributes are removed, the control itself survives
        $this->assertSame(
            '<input type="radio" checked value="">',
            Filter::body('<input type="radio" onmouseover="alert(1)" checked>'),
        );
        // the form element itself is not allowed, only standalone controls
        $this->assertSame(
            '<input type="text">',
            Filter::body('<form action="https://evil.example"><input type="text"></form>'),
        );
        // script remains stripped
        $this->assertSame('', Filter::body('<script>alert(1)</script>'));
        // submit/file input types are not allowed: the control is kept but downgraded to a bare input
        $this->assertSame(
            '<input value="go">',
            Filter::body('<input type="submit" value="go">'),
        );
        $this->assertSame('<input>', Filter::body('<input type="file">'));
    }
}
