<?php

declare(strict_types=1);

namespace CondorcetPHP\Condorcet\Tests\DataManager\DataHandlerDrivers\WoollyDriver;

use CondorcetPHP\Condorcet\Election;
use CondorcetPHP\Condorcet\DataManager\ArrayManager;
use CondorcetPHP\Condorcet\DataManager\DataHandlerDrivers\DataHandlerDriverInterface;
use CondorcetPHP\Condorcet\DataManager\DataHandlerDrivers\WoollyDriver\WoollyDriver;
use CondorcetPHP\Condorcet\Throwable\DataHandlerException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

#[RequiresPhp('8.3')]
#[Group('DataHandlerDrivers')]
class WoollyDriverTest extends TestCase
{
    public function getDriver(): DataHandlerDriverInterface
    {
        return new WoollyDriver;
    }

    protected function hashVotesList(Election $elec): string
    {
        $c = 0;
        $voteCompil = '';
        foreach ($elec->getVotesManager() as $oneVote) {
            $c++;
            $voteCompil .= (string) $oneVote;
        }

        return $c . '||' . hash('md5', $voteCompil);
    }


    public function testManyVoteManipulation(): never
    {
        // Setup
        ArrayManager::$CacheSize = 10;
        ArrayManager::$MaxContainerLength = 10;

        $electionWithDb = new Election;
        $electionInMemory = new Election;
        $electionWithDb->setExternalDataHandler($handlerDriver = $this->getDriver());

        // Run Test

        $electionWithDb->parseCandidates('A;B;C;D;E');
        $electionInMemory->parseCandidates('A;B;C;D;E');

        // 45 Votes
        $votes =   'A > C > B > E * 5
                    A > D > E > C * 5
                    B > E > D > A * 8
                    C > A > B > E * 3
                    C > A > E > B * 7
                    C > B > A > D * 2
                    D > C > E > B * 7
                    E > B > A > D * 8';

        $electionWithDb->parseVotes($votes);
        $electionInMemory->parseVotes($votes);

        expect($handlerDriver->countEntities() + $electionWithDb->getVotesManager()->getContainerSize())->toBe($electionWithDb->countVotes());

        expect($electionWithDb->countVotes())->toBe($electionInMemory->countVotes());
        expect($electionWithDb->getVotesListAsString())->toBe($electionInMemory->getVotesListAsString());
        expect($this->hashVotesList($electionWithDb))->toBe($this->hashVotesList($electionInMemory));

        expect($electionWithDb->getPairwise()->getExplicitPairwise())->toEqual($electionInMemory->getPairwise()->getExplicitPairwise());
        expect((string) $electionWithDb->getWinner('Ranked Pairs Winning'))->toEqual((string) $electionInMemory->getWinner('Ranked Pairs Winning'));
        expect((string) $electionWithDb->getWinner())->toEqual((string) $electionInMemory->getWinner());
        expect((string) $electionWithDb->getCondorcetWinner())->toEqual((string) $electionInMemory->getCondorcetWinner());


        // 58 Votes
        $votes = 'A > B > C > E * 58';

        $electionWithDb->parseVotes($votes);
        $electionInMemory->parseVotes($votes);

        expect($electionWithDb->getVotesManager()->getContainerSize())->toBe(58 % ArrayManager::$MaxContainerLength);
        expect($handlerDriver->countEntities() + $electionWithDb->getVotesManager()->getContainerSize())->toBe($electionWithDb->countVotes());

        expect($electionWithDb->getWinner())->toEqual('A');
        expect((string) $electionWithDb->getWinner())->toEqual((string) $electionInMemory->getWinner());

        expect($electionWithDb->countVotes())->toBe($electionInMemory->countVotes());
        expect($electionWithDb->getVotesListAsString())->toBe($electionInMemory->getVotesListAsString());
        expect($this->hashVotesList($electionWithDb))->toBe($this->hashVotesList($electionInMemory));
        expect($electionWithDb->getVotesManager()->getContainerSize())->toBe(0);
        expect($electionWithDb->getVotesManager()->getCacheSize())->toBeLessThanOrEqual(ArrayManager::$CacheSize);

        // Delete 3 votes
        unset($electionInMemory->getVotesManager()[13]);
        unset($electionInMemory->getVotesManager()[100]);
        unset($electionInMemory->getVotesManager()[102]);
        unset($electionWithDb->getVotesManager()[13]);
        unset($electionWithDb->getVotesManager()[100]);
        unset($electionWithDb->getVotesManager()[102]);

        expect($handlerDriver->countEntities() + $electionWithDb->getVotesManager()->getContainerSize())->toBe($electionWithDb->countVotes());
        expect($electionWithDb->countVotes())->toBe($electionInMemory->countVotes());
        expect($electionWithDb->getVotesListAsString())->toBe($electionInMemory->getVotesListAsString());
        expect($this->hashVotesList($electionWithDb))->toBe($this->hashVotesList($electionInMemory));
        expect($electionWithDb->getVotesManager()->getContainerSize())->toBe(0);
        expect($electionWithDb->getVotesManager()->getCacheSize())->toBeLessThanOrEqual(ArrayManager::$CacheSize);
        expect($electionWithDb->getVotesManager()->debugGetCache())->not()->toHaveKey(13);
        expect($electionWithDb->getVotesManager()->debugGetCache())->not()->toHaveKey(102);
        expect($electionWithDb->getVotesManager()->debugGetCache())->not()->toHaveKey(100);
        expect($electionWithDb->getVotesManager()->debugGetCache())->toHaveKey(101);


        // Unset Handler
        $electionWithDb->removeExternalDataHandler();

        expect($electionWithDb->getVotesManager()->debugGetCache())->toBeEmpty();
        expect($electionWithDb->getVotesManager()->getContainerSize())->toBe($electionInMemory->getVotesManager()->getContainerSize());
        expect($electionWithDb->countVotes())->toBe($electionInMemory->countVotes());
        expect($electionWithDb->getVotesListAsString())->toBe($electionInMemory->getVotesListAsString());
        expect($this->hashVotesList($electionWithDb))->toBe($this->hashVotesList($electionInMemory));

        // Change my mind : Set again the a new handler
        unset($handlerDriver);
        $electionWithDb->setExternalDataHandler($this->getDriver());

        expect($electionWithDb->getVotesManager()->debugGetCache())->toBeEmpty();
        expect($electionWithDb->getVotesManager()->getContainerSize())->toBe(0);
        expect($electionWithDb->countVotes())->toBe($electionInMemory->countVotes());
        expect($electionWithDb->getVotesListAsString())->toBe($electionInMemory->getVotesListAsString());
        expect($this->hashVotesList($electionWithDb))->toBe($this->hashVotesList($electionInMemory));

        expect($electionWithDb->removeExternalDataHandler())->toBeTrue();

        $this->expectException(DataHandlerException::class);
        $this->expectExceptionMessage('Problem with data handler: external data handler cannot be removed, is already in use');

        $electionWithDb->removeExternalDataHandler();
    }

    public function testVotePreserveTag(): void
    {
        // Setup
        ArrayManager::$CacheSize = 10;
        ArrayManager::$MaxContainerLength = 10;

        $electionWithDb = new Election;
        $electionWithDb->setExternalDataHandler($this->getDriver());

        $electionWithDb->parseCandidates('A;B;C');

        $electionWithDb->parseVotes('A > B > C * 5
                                        tag1 || B > A > C * 3');

        expect($electionWithDb->countVotes('tag1', false))->toBe(5);
        expect($electionWithDb->countVotes('tag1', true))->toBe(3);

        $electionWithDb->parseVotes('A > B > C * 5
                                        tag1 || B > A > C * 3');

        expect($electionWithDb->countVotes('tag1', false))->toBe(10);
        expect($electionWithDb->countVotes('tag1', true))->toBe(6);
    }

    public function testVoteObjectIntoDataHandler(): void
    {
        // Setup
        ArrayManager::$CacheSize = 10;
        ArrayManager::$MaxContainerLength = 10;

        $electionWithDb = new Election;
        $electionWithDb->setExternalDataHandler($this->getDriver());

        $electionWithDb->parseCandidates('A;B;C');

        $myVote = $electionWithDb->addVote('A>B>C');

        $electionWithDb->getVotesManager()->regularize();
        expect($electionWithDb->getVotesManager()->getContainerSize())->toBe(0);

        // myVote is no longer a part of the election. Internally, it will work with clones.
        expect($myVote->countLinks())->toBe(0);
        expect($myVote)->not()->toBe($electionWithDb->getVotesList()[0]);
        expect($electionWithDb->getVotesList()[0]->haveLink($electionWithDb))->toBeTrue();
    }

    public function testUpdateEntity(): void
    {
        // Setup
        ArrayManager::$CacheSize = 10;
        ArrayManager::$MaxContainerLength = 10;

        $electionWithDb = new Election;
        $electionWithDb->setExternalDataHandler($this->getDriver());

        $electionWithDb->parseCandidates('A;B;C');

        $electionWithDb->parseVotes('A>B>C * 19');
        $electionWithDb->addVote('C>B>A', 'voteToUpdate');

        $vote = $electionWithDb->getVotesList('voteToUpdate', true)[19];
        $vote->setRanking('B>A>C');
        $vote = null;

        $electionWithDb->parseVotes('A>B>C * 20');

        expect($electionWithDb->getVotesListAsString())->toBe(
            <<<'VOTES'
                A > B > C * 39
                B > A > C * 1
                VOTES
        );
    }

    public function testGetVotesListGenerator(): void
    {
        $electionWithDb = new Election;
        $electionWithDb->setExternalDataHandler($this->getDriver());

        $electionWithDb->parseCandidates('A;B;C');

        $electionWithDb->parseVotes('A>B>C * 10;tag42 || C>B>A * 42');

        $votesListGenerator = [];

        foreach ($electionWithDb->getVotesListGenerator() as $key => $value) {
            $votesListGenerator[$key] = $value;
        }

        expect($votesListGenerator)->toHaveCount(52);


        $votesListGenerator = [];

        foreach ($electionWithDb->getVotesListGenerator('tag42') as $key => $value) {
            $votesListGenerator[$key] = $value;
        }

        expect($votesListGenerator)->toHaveCount(42);
    }

    public function testSliceInput(): void
    {
        // Setup
        ArrayManager::$CacheSize = 462;
        ArrayManager::$MaxContainerLength = 462;

        $electionWithDb = new Election;
        $electionWithDb->setExternalDataHandler($this->getDriver());

        $electionWithDb->parseCandidates('A;B;C');

        $electionWithDb->parseVotes('A>B>C * 463');

        expect($electionWithDb->countVotes())->toBe(463);
    }

    public function testMultipleHandler(): never
    {
        $this->expectException(DataHandlerException::class);
        $this->expectExceptionMessage('external data handler cannot be imported');

        $electionWithDb = new Election;
        $electionWithDb->setExternalDataHandler($this->getDriver());
        $electionWithDb->setExternalDataHandler($this->getDriver());
    }

    public function testEmptyEntities(): void
    {
        $handlerDriver = $this->getDriver();

        expect($handlerDriver->selectOneEntity(500))->toBeFalse();

        expect($handlerDriver->selectRangeEntities(500, 5))->toBe([]);
    }
}
