<?php
declare(strict_types=1);

namespace CondorcetPHP\Condorcet\Benchmarks;

use CondorcetPHP\Condorcet\Algo\Methods\RankedPairs\RankedPairs_Core;
use CondorcetPHP\Condorcet\Election;
use PhpBench\Attributes as Bench;
ini_set('memory_limit','51200M');

class PairwiseNumberOfCandidatesBench
{
    public array $numberOfCandidates = [3,5,7,10,20,30,40,50,60,70,80,90,100];
    public array $numberOfVotes = [10,100,1000,10000];


    protected Election $election;

    public function __construct ()
    {
        RankedPairs_Core::$MaxCandidates = null;
    }


    protected function buildElection (int $numberOfCandidates, int $numberOfVotes): void
    {
        $this->election = $election = new Election;
        $this->election->setNumberOfSeats((int) ($numberOfCandidates / 3));

        $candidates = [];

        for ($i=0 ; $i < $numberOfCandidates ; $i++) :
            $candidates[] = $election->addCandidate();
        endfor;

        for ($i = 0 ; $i < $numberOfVotes ; $i++) :
            $oneVote = $candidates;
            shuffle($oneVote);

            $election->addVote($oneVote);
        endfor;
    }

    public function provideNumberOfCandidates (): \Generator
    {
        foreach ($this->numberOfCandidates as $n) :
            yield $n => ['numberOfCandidates' => $n];
        endforeach;
    }

    public function provideNumberOfVotes (): \Generator
    {
        foreach ($this->numberOfVotes as $n) :
            yield $n => ['numberOfVotes' => $n];
        endforeach;
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\ParamProviders(['provideNumberOfCandidates', 'provideNumberOfVotes'])]
    #[Bench\Warmup(0)]
    #[Bench\Iterations(1)]
    #[Bench\Revs(1)]
    public function benchByCandidates (array $params): void
    {
        $this->buildElection($params['numberOfCandidates'], $params['numberOfVotes']);
    }
}