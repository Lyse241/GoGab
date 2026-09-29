<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function numbers(): array
    {
        return [
            'déjà formaté' => ['077 12 34 56', '077 12 34 56'],
            'sans espaces' => ['077123456', '077 12 34 56'],
            'tirets et points' => ['077-12.34-56', '077 12 34 56'],
            '+241 et 8 chiffres' => ['+241 77 12 34 56', '077 12 34 56'],
            '+241 et 9 chiffres' => ['+241 077 12 34 56', '077 12 34 56'],
            '00241' => ['00241 66 20 00 01', '066 20 00 01'],
            'trop court : laissé tel quel' => [' 12 34 ', '12 34'],
            'vide' => ['', ''],
            'null' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }
}
