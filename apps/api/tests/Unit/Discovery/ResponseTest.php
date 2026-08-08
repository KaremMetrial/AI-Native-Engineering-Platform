<?php

declare(strict_types=1);

namespace Tests\Unit\Discovery;

use App\Discovery\Domain\Response;
use Tests\TestCase;

class ResponseTest extends TestCase
{
    public function test_record_creates_a_response_bound_to_a_question(): void
    {
        $response = Response::record('response-1', 'tenant-1', 'question-1', 'We lose bids on turnaround time.', 'user-1');

        $this->assertSame('question-1', $response->questionId);
        $this->assertSame('We lose bids on turnaround time.', $response->content);
        $this->assertSame('user-1', $response->respondedBy);
    }
}
