<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Blade only reads "@directive" when the character before the @ is not a letter, digit or underscore.
 * "…@endbusinessCan@endif" or "section@if(…)" is therefore printed as text, and a missing @endif breaks the
 * whole page. Put a space or a line break before such a directive.
 */
class BladeDirectiveSpacingTest extends TestCase
{
    private const DIRECTIVES = 'if|elseif|else|endif|unless|endunless|isset|endisset|empty|endempty|foreach|endforeach|forelse|endforelse|for|endfor|while|endwhile|switch|case|endswitch'
        . '|php|endphp|businessCan|endbusinessCan|can|cannot|endcan|endcannot|auth|endauth|guest|endguest|error|enderror|section|endsection|push|endpush|once|endonce'
        . '|include|includeIf|selected|checked|disabled|readonly|required|csrf|method|json|break|continue';

    public function test_no_directive_is_glued_to_the_word_before_it(): void
    {
        $views = dirname(__DIR__, 2) . '/resources/views';
        $problems = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views)) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                // Skip e-mail addresses such as name@example.com.
                if (preg_match_all('/\w@(' . self::DIRECTIVES . ')\b(?![.\w-]*\.(com|org|test|co|io|net)\b)/', $line, $m)) {
                    $problems[] = str_replace($views . '/', '', $file->getPathname()) . ':' . ($n + 1) . ' → @' . implode(', @', $m[1]);
                }
            }
        }

        $this->assertSame([], $problems, "Blade directives glued to the word before them are not compiled:\n" . implode("\n", $problems));
    }
}
