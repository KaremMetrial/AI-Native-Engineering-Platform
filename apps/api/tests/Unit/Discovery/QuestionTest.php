<?php

declare(strict_types=1);

namespace Tests\Unit\Discovery;

use App\Discovery\Domain\Question;
use DomainException;
use Tests\TestCase;

class QuestionTest extends TestCase
{
    public function test_ask_creates_a_question_with_the_given_sequence(): void
    {
        $question = Question::ask('question-1', 'tenant-1', 'session-1', 'What problem are you solving?', 1, 'user-1');

        $this->assertSame(1, $question->sequence);
        $this->assertSame('What problem are you solving?', $question->prompt);
    }

    public function test_sequence_must_be_a_positive_integer(): void
    {
        $this->expectException(DomainException::class);

        Question::ask('question-1', 'tenant-1', 'session-1', 'What problem are you solving?', 0, 'user-1');
    }
}
