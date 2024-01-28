<?php

declare(strict_types=1);

namespace CondorcetPHP\Condorcet\Tests;

use CondorcetPHP\Condorcet\ElectionProcess\ElectionState;
use CondorcetPHP\Condorcet\{Candidate, Condorcet, Election, Vote};
use CondorcetPHP\Condorcet\Tests\Datasets\MethodsDatasets;
use CondorcetPHP\Condorcet\Throwable\{CandidateDoesNotExistException, CandidateExistsException, ElectionObjectVersionMismatchException, FileDoesNotExistException, NoCandidatesException, NoSeatsException, ParseVotesMaxNumberReachedException, ResultRequestedWithoutVotesException, VoteException, VoteInvalidFormatException, VoteMaxNumberReachedException, VotingHasStartedException};
use CondorcetPHP\Condorcet\Tools\Converters\CEF\CondorcetElectionFormat;
use PHPUnit\Framework\Attributes\{DataProviderExternal};
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ElectionTest extends TestCase
{
    private Election $election1;
    private Election $election2;

    private Candidate $candidate1;
    private Candidate $candidate2;
    private Candidate $candidate3;

    private Vote $vote1;
    private Vote $vote2;
    private Vote $vote3;
    private Vote $vote4;

    protected function setUp(): void
    {
        $this->election1 = new Election;

        $this->candidate1 = $this->election1->addCandidate('candidate1');
        $this->candidate2 = $this->election1->addCandidate('candidate2');
        $this->candidate3 = $this->election1->addCandidate('candidate3');

        $this->election1->addVote($this->vote1 = new Vote([$this->candidate1, $this->candidate2, $this->candidate3]));
        $this->election1->addVote($this->vote2 = new Vote([$this->candidate2, $this->candidate3, $this->candidate1]));
        $this->election1->addVote($this->vote3 = new Vote([$this->candidate3, $this->candidate1, $this->candidate2]));
        $this->election1->addVote($this->vote4 = new Vote([$this->candidate1, $this->candidate2, $this->candidate3]));

        $this->election2 = new Election;
    }

    protected function tearDown(): void
    {
        Election::setMaxParseIteration((new ReflectionClass(Election::class))->getProperty('maxParseIteration')->getDefaultValue());
        Election::setMaxVoteNumber((new ReflectionClass(Election::class))->getProperty('maxVoteNumber')->getDefaultValue());
    }

    public function testRemoveAllVotes(): void
    {
        expect($this->election1->removeAllVotes())->toBeTrue();
        expect($this->election1->getVotesList())->toHaveCount(0);
        expect($this->election1->countVotes())->toBe(0);
    }

    public function testRemoveVotes(): never
    {
        expect($this->election1->removeVote($this->vote2))->toBeTrue();
        expect($this->election1->getVotesList())->toHaveCount(3);

        $badRemoveVote = new Vote('A');

        $this->expectException(VoteException::class);
        $this->expectExceptionMessage('Problem handling vote: Cannot remove vote not registered in this election');

        $this->election1->removeVote($badRemoveVote);
    }

    public function testRemoveVotesByTags(): void
    {
        $this->vote1->addtags('tag1,tag2,tag3');
        $this->vote2->addtags('tag3,tag4');
        $this->vote3->addtags('tag3,tag4,tag5');
        $this->vote4->addtags('tag1,tag4');

        expect($r = $this->election1->removeVotesByTags(['tag1', 'tag5']))->toHaveCount(3);

        expect($r)->toBe([$this->vote1, $this->vote3, $this->vote4]);

        expect($this->election1->getVotesList())->toBe([1 => $this->vote2]);

        $this->setUp();

        $this->vote1->addtags('tag1,tag2,tag3');
        $this->vote2->addtags('tag3,tag4');
        $this->vote3->addtags('tag3,tag4,tag5');
        $this->vote4->addtags('tag1,tag4');

        expect($r = $this->election1->removeVotesByTags('tag1,tag5', false))->toHaveCount(1);

        expect($r)->toBe([$this->vote2]);

        expect($this->election1->getVotesList())->toBe([0 => $this->vote1, 2 => $this->vote3, 3 => $this->vote4]);
    }


    public function testTagsFilter(): void
    {
        $this->vote1->addtags('tag1,tag2,tag3');
        $this->vote2->addtags('tag3,tag4');
        $this->vote3->addtags('tag3,tag4,tag5');
        $this->vote4->addtags('tag1,tag4');

        expect([0 => $this->vote1, 3 => $this->vote4])->toBe($this->election1->getVotesList('tag1,tag2', true));
        expect($this->election1->countVotes('tag1,tag2', true))->toBe(2);

        expect([1 => $this->vote2, 2 => $this->vote3])->toBe($this->election1->getVotesList('tag1,tag2', false));
        expect($this->election1->countVotes('tag1,tag2', false))->toBe(2);

        $resultGlobal = $this->election1->getResult('Schulze');
        $resultFilter1 = $this->election1->getResult('Schulze', ['tags' => 'tag1', 'withTag' => true]);
        $resultFilter2 = $this->election1->getResult('Schulze', ['tags' => 'tag1', 'withTag' => false]);

        expect($resultFilter1)->not()->toBe($resultGlobal);
        expect($resultFilter2)->not()->toBe($resultGlobal);
        expect($resultFilter2)->not()->toBe($resultFilter1);
    }

    public function testParseCandidates(): void
    {
        expect($this->election2->parseCandidates('Bruckner;   Mahler   ;
                                                        Debussy
                                                        Bibendum'))
            ->toHaveCount(4);

        expect($this->election2->getCandidatesListAsString())->toBe(['Bruckner', 'Mahler', 'Debussy', 'Bibendum']);
    }

    public function testgetCandidateObjectFromName(): void
    {
        expect($this->election1->getCandidateObjectFromName('candidate1'))->toBe($this->candidate1);
        expect($this->election1->getCandidateObjectFromName('candidate42'))->toBeNull();
    }

    public function testParseError(): never
    {
        $this->expectException(VoteInvalidFormatException::class);
        $this->expectExceptionMessage("The format of the vote is invalid: the value 'text' is not an integer.");

        $this->election1->parseVotes('candidate1>candidate2 * text');
    }

    public function testMaxParseIteration1(): never
    {
        expect(Election::setMaxParseIteration(42))->toBe(42);

        expect($this->election1->parseVotes('candidate1>candidate2 * 42'))->toBe(42);

        expect($this->election1->parseVotes('candidate1>candidate2 * 42'))->toBe(42);

        expect(Election::setMaxParseIteration(null))->toBeNull();
        expect($this->election1->parseVotes('candidate1>candidate2 * 43'))->toBe(43);

        expect(Election::setMaxParseIteration(42))->toBe(42);

        $this->expectException(ParseVotesMaxNumberReachedException::class);
        $this->election1->parseVotes('candidate1>candidate2 * 43');
    }

    public function testMaxParseIteration2(): never
    {
        expect(Election::setMaxParseIteration(42))->toBe(42);

        $this->expectException(ParseVotesMaxNumberReachedException::class);

        expect($this->election1->parseVotes('
            candidate1>candidate2 * 21
            candidate1>candidate2 * 21
            candidate1>candidate2 * 21
        '))->toBe(42);
    }

    public function testMaxParseIteration3(): never
    {
        expect(Election::setMaxParseIteration(2))->toBe(2);

        expect($this->election2->parseCandidates('candidate1;candidate2'))->toBe([0 => 'candidate1', 1 => 'candidate2']);

        expect($this->election2->parseCandidates('candidate3;candidate4'))->toBe([0 => 'candidate3', 1 => 'candidate4']);

        expect(Election::setMaxParseIteration(null))->toBeNull();
        expect($this->election2->parseCandidates('candidate5;candidate6;candidate7'))->toBe([0 => 'candidate5', 1 => 'candidate6', 2 => 'candidate7']);

        expect(Election::setMaxParseIteration(2))->toBe(2);

        $this->expectException(VoteMaxNumberReachedException::class);
        $this->expectExceptionMessage('The maximal number of votes for the method is reached: 2');

        $this->election2->parseCandidates('candidate8;candidate9;candidate10');
    }

    public function testMaxVoteNumber(): never
    {
        $election = new Election;
        expect($election->parseCandidates('candidate1;candidate2;candidate3'))->toHaveCount(3);

        expect(Election::setMaxVoteNumber(42))->toBe(42);

        expect($election->parseVotes('candidate1>candidate2 * 21'))->toBe(21);

        try {
            $election->parseVotes('candidate1>candidate2 * 42');
            expect(false)->toBeTrue();
        } catch (VoteMaxNumberReachedException $e) {
            expect($e->getMessage())->toEqual('The maximal number of votes for the method is reached');
        }

        expect($election->countVotes())->toBe(21);

        $election->parseVotes('candidate1 * 21');

        expect($election->countVotes())->toBe(42);

        expect(Election::setMaxVoteNumber(null))->toBeNull();
        $election->addVote('candidate3');

        expect(Election::setMaxVoteNumber(42))->toBe(42);

        try {
            $election->addVote('candidate3');
        } catch (VoteMaxNumberReachedException $e) {
            $reserveException = $e;
        }

        expect(Election::setMaxVoteNumber(null))->toBeNull();
        $this->expectException(VoteMaxNumberReachedException::class);
        $this->expectExceptionMessage('The maximal number of votes for the method is reached');

        throw $reserveException;
    }

    public function testGetVotesListAsString(): void
    {
        $this->election1 = new Election;

        $this->election1->addCandidate('C');
        $this->election1->addCandidate('B');
        $this->election1->addCandidate('D');
        $this->election1->addCandidate('E');
        $this->election1->addCandidate('A');

        $this->election1->parseVotes('
            D * 6
            A * 6
            E = A = B *3
            A > C = B > E * 5
            Y > Z
        ');

        expect($this->election1->getVotesListAsString())->toBe("A > B = C = D = E * 6\n" .
    "D > A = B = C = E * 6\n" .
    "A > B = C > E > D * 5\n" .
    "A = B = E > C = D * 3\n" .
    'A = B = C = D = E * 1');

        $this->election1->setImplicitRanking(false);

        expect($this->election1->getVotesListAsString())->toBe("A * 6\n" .
    "D * 6\n" .
    "A > B = C > E * 5\n" .
    "A = B = E * 3\n" .
    '/EMPTY_RANKING/ * 1');

        expect($this->election1->getVotesListAsString(false))->toBe(<<<'VOTES'
            A * 6
            D * 6
            A > B = C > E * 5
            A = B = E * 3
            Y > Z * 1
            VOTES);
    }

    public function testEmptyRankingExport(): void
    {
        $this->election2->parseCandidates('A;B;C');
        $this->election2->setImplicitRanking(false);

        $this->election2->addVote(new Vote(''));
        $this->election2->addVote(new Vote('D>E'));

        expect($this->election2->getVotesListAsString(true))->toBe('/EMPTY_RANKING/ * 2');
        expect($this->election2->getVotesListAsString(false))->toBe('/EMPTY_RANKING/ * 1' . "\n" . 'D > E * 1');

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: true, inContext: false))->toBe($cvotes_explicit_without_context =
                        <<<'CVOTES'
                            #/Candidates: A ; B ; C
                            #/Implicit Ranking: false
                            #/Weight Allowed: false

                            /EMPTY_RANKING/ * 1
                            D > E * 1
                            CVOTES);

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: false, inContext: false))->toBe(str_replace(' * 1', '', $cvotes_explicit_without_context));

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: true, inContext: true))
            ->toBe(
                <<<'CVOTES'
                    #/Candidates: A ; B ; C
                    #/Implicit Ranking: false
                    #/Weight Allowed: false

                    /EMPTY_RANKING/ * 2
                    CVOTES
            );

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: false, inContext: true))->toBe(<<<'CVOTES'
            #/Candidates: A ; B ; C
            #/Implicit Ranking: false
            #/Weight Allowed: false

            /EMPTY_RANKING/
            /EMPTY_RANKING/
            CVOTES);

        $this->election2->setImplicitRanking(true);

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: true, inContext: true))->toBe(<<<'CVOTES'
            #/Candidates: A ; B ; C
            #/Implicit Ranking: true
            #/Weight Allowed: false

            A = B = C * 2
            CVOTES);

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: false, inContext: true))->toBe(<<<'CVOTES'
            #/Candidates: A ; B ; C
            #/Implicit Ranking: true
            #/Weight Allowed: false

            A = B = C
            A = B = C
            CVOTES);

        $this->election2 = new Election;
        $this->election2->parseCandidates('A;B;C;D');
        $this->election2->setImplicitRanking(true);

        $this->election2->addVote(new Vote('A>B'));

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: true, inContext: true))->toBe(<<<'CVOTES'
            #/Candidates: A ; B ; C ; D
            #/Implicit Ranking: true
            #/Weight Allowed: false

            A > B > C = D * 1
            CVOTES);

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: true, inContext: false))->toBe($cvotes_implicit_without_context =
                        <<<'CVOTES'
                            #/Candidates: A ; B ; C ; D
                            #/Implicit Ranking: true
                            #/Weight Allowed: false

                            A > B * 1
                            CVOTES);

        expect(CondorcetElectionFormat::createFromElection(election: $this->election2, includeNumberOfSeats: false, aggregateVotes: false, inContext: false))->toBe(str_replace(' * 1', '', $cvotes_implicit_without_context));
    }

    public function testParseVoteCandidateCoherence(): void
    {
        $this->election1 = new Election;

        $cA = $this->election1->addCandidate('A');
        $cB = $this->election1->addCandidate('B');
        $cC = $this->election1->addCandidate('C');

        expect($this->election1->parseVotes('
            A>B>C * 2
        '))->toBe(2);

        $votes = $this->election1->getVotesList();

        foreach ($votes as $vote) {
            $ranking = $vote->getRanking();

            expect($ranking[1][0])->toBe($cA);
            expect($ranking[2][0])->toBe($cB);
            expect($ranking[3][0])->toBe($cC);
        }
    }

    public function testParseVotesInvalidPath(): void
    {
        $this->election1 = new Election;

        $this->election1->addCandidate('A');
        $this->election1->addCandidate('B');

        $this->expectException(FileDoesNotExistException::class);
        $this->expectExceptionMessageMatches('/bad_file.txt$/');

        $this->election1->parseVotes('bad_file.txt', true);
    }

    public function testParseVotesWithoutFail(): void
    {
        $this->election1 = new Election;

        $this->election1->addCandidate('A');
        $this->election1->addCandidate('B');
        $this->election1->addCandidate('C');

        expect($this->election1->parseVotesWithoutFail('
            A > B > C
            A > B > C * 4;tag1 || A > B > C*4 #Coucou
            A < B < C * 10
            D <> B
            A > B > C
        '))->toBe(2);

        expect($this->election1->countVotes())->toBe(10);

        expect($this->election1->parseVotesWithoutFail(__DIR__ . '/../LargeElectionData/smallVote1.votes', true))->toBe(2);

        expect($this->election1->countVotes())->toBe(20);

        expect($this->election1->parseVotesWithoutFail(new \SplFileObject(__DIR__ . '/../LargeElectionData/smallVote1.votes'), true))->toBe(2);

        expect($this->election1->countVotes())->toBe(30);

        expect($this->election1->parseVotesWithoutFail(new \SplFileInfo(__DIR__ . '/../LargeElectionData/smallVote1.votes'), true))->toBe(2);

        expect($this->election1->countVotes())->toBe(40);
    }

    public function testParseVotesWithoutFailInvalidPath(): void
    {
        $this->election1 = new Election;

        $this->election1->addCandidate('A');
        $this->election1->addCandidate('B');

        $this->expectException(FileDoesNotExistException::class);
        $this->expectExceptionMessageMatches('/bad_file.txt$/');

        $this->election1->parseVotesWithoutFail('bad_file.txt', true);
    }

    public function testVoteWeight(): void
    {
        $election = new Election;

        $election->addCandidate('A');
        $election->addCandidate('B');
        $election->addCandidate('C');
        $election->addCandidate('D');

        $election->parseVotes('
            tag1 || A > C > D * 6
            tag2 || B > A > D * 1
            C > B > D * 3
            D > B > A * 3
        ');

        $voteWithWeight = $election->addVote('D > C > B');
        $voteWithWeight->setWeight(2);

        expect($election->sumVotesWeight())->toBe(14);

        expect($election->sumValidVotesWeightWithConstraints())->toBe($election->sumVotesWeight());

        // Some test about votes weight tags filters
        expect($election->sumVotesWeight('tag1,tag2'))->toBe(7);

        expect($election->sumVotesWeight('tag2', false))->toBe(13);

        expect($election->sumVotesWeight('tag1,tag2', 2))->toBe(0);

        // Continue
        expect((string) $voteWithWeight)->toBe('D > C > B ^2');

        expect($voteWithWeight->getSimpleRanking($election))->toBe('D > C > B > A');

        expect($election->getResult('Schulze Winning')->getResultAsString())->not()->toBe('A = D > C > B');

        $election->allowsVoteWeight(true);

        expect($election->sumVotesWeight())->toBe(15);

        expect($voteWithWeight->getSimpleRanking($election))->toBe('D > C > B > A ^2');

        expect($election->getResult('Schulze Winning')->getResultAsString())->toBe('A = D > C > B');

        $election->allowsVoteWeight(false);

        expect($election->sumVotesWeight())->toBe(14);

        expect($election->getResult('Schulze Winning')->getResultAsString())->not()->toBe('A = D > C > B');

        $election->allowsVoteWeight(!$election->isVoteWeightAllowed());

        $election->removeVote($voteWithWeight);

        expect($election->sumVotesWeight())->toBe(13);

        $election->parseVotes('
            D > C > B ^2 * 1
        ');

        expect($election->sumVotesWeight())->toBe(15);

        expect($election->getResult('Schulze Winning')->getResultAsString())->toBe('A = D > C > B');

        $election->addVote('D > C > B');

        expect($election->getVotesListAsString())
            ->toBe(
                <<<'VOTES'
                    A > C > D > B * 6
                    C > B > D > A * 3
                    D > B > A > C * 3
                    D > C > B > A ^2 * 1
                    B > A > D > C * 1
                    D > C > B > A * 1
                    VOTES
            );
    }

    public function testaddVotesFromJson(): never
    {
        $election = new Election;

        $election->addCandidate('A');
        $election->addCandidate('B');
        $election->addCandidate('C');

        $votes = [];

        $votes[]['vote'] = 'B>C>A';
        $votes[]['vote'] = new \stdClass; // Invalid Vote
        $votes[]['vote'] = ['C', 'B', 'A'];

        expect($election->addVotesFromJson(json_encode($votes)))->toBe(2);

        expect($election->getVotesListAsString())->toBe(<<<'VOTES'
            B > C > A * 1
            C > B > A * 1
            VOTES);

        $votes = [];

        $votes[0]['vote'] = 'A>B>C';
        $votes[0]['multi'] = 5;
        $votes[0]['tag'] = 'tag1';
        $votes[0]['weight'] = '42';

        $election->addVotesFromJson(json_encode($votes));

        $election->allowsVoteWeight(true);

        expect($election->getVotesListAsString())->toBe(<<<'VOTES'
            A > B > C ^42 * 5
            B > C > A * 1
            C > B > A * 1
            VOTES);

        expect($election->countVotes('tag1'))->toBe(5);

        $this->expectException(\JsonException::class);

        $election->addVotesFromJson(json_encode($votes) . '{42');
    }

    public function testaddCandidatesFromJson(): never
    {
        $election = new Election;

        $candidates = ['candidate1 ', 'candidate2'];

        $election->addCandidatesFromJson(json_encode($candidates));

        expect($election->countCandidates())->toBe(2);

        expect($election->getCandidatesListAsString())->toBe(['candidate1', 'candidate2']);

        $this->expectException(CandidateExistsException::class);
        $this->expectExceptionMessage('This candidate already exists: candidate2');

        $election->addCandidatesFromJson(json_encode(['candidate2']));
    }

    public function testaddCandidatesFromInvalidJson(): never
    {
        $election = new Election;

        $this->expectException(\JsonException::class);
        $election->addCandidatesFromJson(json_encode(['candidate3']) . '{42');
    }


    public function testaddVotesFromJsonWithInvalidJson(): void
    {
        $errors = ['42', 'true', 'false', 'null', '', ' ', json_encode(new \stdClass)];

        foreach ($errors as $oneError) {
            try {
                $this->election2->addVotesFromJson($oneError);

                // Else fail
                $this->fail("{$oneError} is not a valid Json for PHP");
            } catch (\JsonException) {
            }
        }

        expect($this->election2->getVotesList())->toBeEmpty();
    }

    public function testCachingResult(): void
    {
        $election = new Election;

        $election->addCandidate('A');
        $election->addCandidate('B');
        $election->addCandidate('C');
        $election->addCandidate('D');

        $election->addVote($vote1 = new Vote('A > C > D'));

        $result1 = $election->getResult('Schulze');
        expect($election->getResult('Schulze'))->toBe($result1);
    }

    public function testElectionSerializing(): void
    {
        $election = new Election;

        $election->addCandidate('A');
        $election->addCandidate('B');
        $election->addCandidate('C');
        $election->addCandidate('D');

        $election->addVote($vote1 = new Vote('A > C > D'));
        $result1 = $election->getResult('Schulze');

        $election = serialize($election);
        // file_put_contents("Tests/src/ElectionData/serialized_election_v3.2.0.txt", $election); # For next test
        $election = unserialize($election);

        expect($election->getResult('Schulze'))->not()->toBe($result1);
        expect($election->getResult('Schulze')->getResultAsString())->toBe($result1->getResultAsString());

        expect($election->getVotesList()[0])->not()->toBe($vote1);
        expect($election->getVotesList()[0]->getSimpleRanking())->toBe($vote1->getSimpleRanking());
        expect($election->getVotesList()[0]->haveLink($election))->toBeTrue();
        expect($vote1->haveLink($election))->toBeFalse();
    }

    public function testElectionUnserializing(): void
    {
        $this->expectException(ElectionObjectVersionMismatchException::class);
        $this->expectExceptionMessage(
            "Version mismatch: The election object has version '3.2' " .
            "which is different from the current class version '" . Condorcet::getVersion(true) . "'"
        );

        unserialize(
            file_get_contents('Tests/src/ElectionData/serialized_election_v3.2.0.txt')
        );
    }

    public function testElectionUnserializingWithoutVotes(): void
    {
        $e = new Election;
        $e->parseCandidates('A;B');
        $e = serialize($e);
        $e = unserialize($e);

        expect($e)->toBeInstanceOf(Election::class);
    }

    public function testCloneElection(): void
    {
        $this->election1->computeResult();

        $cloneElection = clone $this->election1;

        expect($cloneElection->getVotesManager())->not()->toBe($this->election1->getVotesManager());
        expect($cloneElection->getVotesList())->toBe($this->election1->getVotesList());

        expect($cloneElection->getCandidatesList())->toBe($this->election1->getCandidatesList());

        expect($cloneElection->getPairwise())->not()->toBe($this->election1->getPairwise());
        expect($cloneElection->getExplicitPairwise())->toEqual($this->election1->getExplicitPairwise());

        expect($cloneElection->getTimerManager())->not()->toBe($this->election1->getTimerManager());

        expect($cloneElection->getVotesList()[0])->toBe($this->election1->getVotesList()[0]);

        expect($cloneElection->getVotesList()[0]->haveLink($this->election1))->toBeTrue();
        expect($cloneElection->getVotesList()[0]->haveLink($cloneElection))->toBeTrue();
    }

    public function testPairwiseArrayAccess(): void
    {
        $this->election1->computeResult();

        expect($this->election1->getPairwise()->offsetExists(1))->toBeTrue();
    }

    public function testGetCandidateObjectFromKey(): void
    {
        expect($this->election1->getCandidateObjectFromKey(1))->toBe($this->candidate2);

        expect($this->election1->getCandidateObjectFromKey(42))->toBeNull();
    }

    public function testElectionState1(): never
    {
        $this->expectException(VotingHasStartedException::class);
        $this->expectExceptionMessage("The voting has started: cannot add 'candidate4'");

        $this->election1->addCandidate('candidate4');
    }

    public function testElectionState2(): never
    {
        $this->expectException(VotingHasStartedException::class);
        $this->expectExceptionMessage('The voting has started');

        $this->election1->removeCandidates('candidate4');
    }

    public function testElectionState3(): never
    {
        $this->expectException(NoCandidatesException::class);
        $this->expectExceptionMessage('You need to specify one or more candidates before voting');

        $election = new Election;
        $election->setStateTovote();
    }

    public function testElectionState4(): never
    {
        $this->expectException(ResultRequestedWithoutVotesException::class);
        $this->expectExceptionMessage('The result cannot be requested without votes');

        $election = new Election;
        $election->getResult();
    }

    public function testElectionState5(): void
    {
        $this->election1->getResult();

        expect($this->election1->setStateTovote())->toBeTrue();

        expect($this->election1->getState())->toBe(ElectionState::VOTES_REGISTRATION);
    }

    public function testAddSameVote(): never
    {
        $this->expectException(VoteException::class);
        $this->expectExceptionMessage('This vote is already linked to the election');

        $this->election1->addVote($this->vote1);
    }

    public function testDestroy(): void
    {
        $election = new Election;

        $election->addCandidate('candidate1');
        $election->addCandidate('candidate2');
        $election->addCandidate('candidate3');

        $election->addVote('candidate1>candidate2');

        $weakref = \WeakReference::create($election);

        // PHP circular reference can bug
        // \debug_zval_dump($election);
        unset($election);

        expect($weakref->get())->toBeNull();
    }

    public function testRemoveCandidate(): never
    {
        $this->expectException(CandidateDoesNotExistException::class);
        $this->expectExceptionMessage('This candidate does not exist: B');

        $election = new Election;

        $election->addCandidate('candidate1');

        $badCandidate = new Candidate('B');

        $election->removeCandidates($badCandidate);
    }

    #[DataProviderExternal(MethodsDatasets::class, 'MethodsListProvider')]
    public function testRemoveCandidateResult(string $method): void
    {
        $votes = '  Memphis * 4
                    Nashville * 3
                    Chattanooga * 2
                    Knoxville * 1';

        // Ref
        $electionRef = new Election;

        $electionRef->addCandidate('Memphis');
        $electionRef->addCandidate('Nashville');
        $electionRef->addCandidate('Knoxville');
        $electionRef->addCandidate('Chattanooga');

        $electionRef->parseVotes($votes);

        // Test
        $electionTest = new Election;

        $electionTest->addCandidate('Memphis');
        $electionTest->addCandidate('BadCandidate');
        $electionTest->addCandidate('Nashville');
        $electionTest->addCandidate('Knoxville');
        $electionTest->addCandidate('Chattanooga');

        $electionTest->removeCandidates('BadCandidate');

        $electionTest->parseVotes($votes);


        expect($electionTest->getResult($method)->getResultAsArray(true))->toBe($electionRef->getResult($method)->getResultAsArray(true));
    }

    public function testAmbiguousCandidatesOnElectionSide(): never
    {
        $this->expectException(VoteInvalidFormatException::class);
        $this->expectExceptionMessage('The format of the vote is invalid');

        $vote = new Vote('candidate1>candidate2');

        $election1 = new Election;
        $election2 = new Election;

        $election1->addCandidate(new Candidate('candidate2'));
        $election2->addCandidate(new Candidate('candidate1'));

        $election1->addVote($vote);
        $election2->addVote($vote);
    }

    public function testAmbiguousCandidatesOnVoteSide(): never
    {
        $this->expectException(VoteInvalidFormatException::class);
        $this->expectExceptionMessage('The format of the vote is invalid: vote does not match candidate in this election');

        $election1 = new Election;
        $election2 = new Election;

        $election1->addCandidate(new Candidate('candidate1'));
        $election2->addCandidate(new Candidate('candidate2'));

        $candidate3 = new Candidate('candidate3');
        $election1->addCandidate($candidate3);
        $election2->addCandidate($candidate3);

        $vote = new Vote('candidate3');

        $election1->addVote($vote);
        $election2->addVote($vote);

        $election1->getResult();
        $election2->getResult();

        try {
            $vote->setRanking('candidate1>candidate2>candidate3');
        } catch (\Exception $e) {
        }

        expect($election1->debugGetCalculator())->toBeEmpty();
        expect($election2->debugGetCalculator())->toBeEmpty();

        throw $e;
    }

    public function testInvalidSeats(): never
    {
        $this->expectException(NoSeatsException::class);
        $this->expectExceptionMessage('No seats defined');

        $this->election1->setNumberOfSeats(0);
    }

    public function testSeats(): void
    {
        expect($this->election1->getNumberOfSeats())->toBe(100);

        $this->election1->setNumberOfSeats(5);

        expect($this->election1->getNumberOfSeats())->toBe(5);
    }
}
