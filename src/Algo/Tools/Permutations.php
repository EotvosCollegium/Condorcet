<?php
/*
    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);

namespace CondorcetPHP\Condorcet\Algo\Tools;

use Brick\Math\BigInteger;
use Brick\Math\Exception\IntegerOverflowException;
use CondorcetPHP\Condorcet\Throwable\Internal\IntegerOverflowException as CondorcetIntegerOverflowException;
use CondorcetPHP\Condorcet\Dev\CondorcetDocumentationGenerator\CondorcetDocAttributes\InternalModulesAPI;
use CondorcetPHP\Condorcet\Throwable\Internal\CondorcetInternalException;
use SplFixedArray;

// Thanks to Jorge Gomes @cyberkurumin
#[InternalModulesAPI]
class Permutations
{
    #[PublicAPI] // Must be available with composer installation. Only appliez to getPossibleCountOfPermutations() method. PHP and memory can't do the compute() with such large numbers.
    static bool $useBigIntegerIfAvailable = true;

    protected readonly int $candidates_count;
    protected int $arrKey = 0;

    public static function getPossibleCountOfPermutations (int $candidatesNumber): int
    {
        if ($candidatesNumber < 1) :
            throw new CondorcetInternalException('Parameters invalid');
        endif;

        if (self::$useBigIntegerIfAvailable && \class_exists('Brick\Math\BigInteger')) :
            $result = BigInteger::of($candidatesNumber);

            for ($iteration = 1; $iteration < $candidatesNumber; $iteration++) :
                $result = $result->multipliedBy($candidatesNumber - $iteration);
            endfor;

            try {
                return $result->toInt();
            } catch (IntegerOverflowException $e) {
                throw new CondorcetIntegerOverflowException($e->getMessage());
            }
        else :
            $result = $candidatesNumber;

            for ($iteration = 1 ; $iteration < $candidatesNumber ; $iteration++) :
                $result = $result * ($candidatesNumber - $iteration);
            endfor;

            if (\is_float($result)) :
                throw new CondorcetIntegerOverflowException;
            else :
                return $result;
            endif;
        endif;
    }

    public function __construct (int $candidates_count)
    {
        $this->candidates_count = $candidates_count;
    }

    public function getResults (): SplFixedArray
    {
        $results = new SplFixedArray(self::getPossibleCountOfPermutations($this->candidates_count));

        foreach ($this->getPermutationGenerator($this->createCandidates()) as $onePermutation) :
            $results[$this->arrKey++] = $onePermutation;
        endforeach;

        return $results;
    }

    public function writeResults (\SplFileObject $file): void
    {
        $file->rewind();

        foreach ($this->getPermutationGenerator() as $onePermutation) :
            $file->fputcsv($onePermutation);
        endforeach;
    }

    public function getPermutationGenerator (): \Generator
    {
        $this->arrKey = 0;

        return $this->permutationGenerator($this->createCandidates());
    }

    protected function createCandidates (): array
    {
        $arr = [];

        for ($i = 0 ; $i < $this->candidates_count ; $i++) :
            $arr[] = $i;
        endfor;

        return $arr;
    }

    protected function permutationGenerator (array $elements) : \Generator
    {
        if (count($elements) <= 1) :
            yield [1 => \reset($elements)]; // Set the only key to index 1
        else :
            foreach ($this->permutationGenerator(\array_slice($elements, 1)) as $permutation) :
                foreach (\range(0, \count($elements) - 1) as $i) :
                    $r = \array_merge(
                        \array_slice($permutation, 0, $i),
                        [$elements[0]],
                        \array_slice($permutation, $i)
                    );

                    // Set first key to 1
                    $r = [null, ...$r];
                    unset($r[0]);

                    yield $r;
                endforeach;
            endforeach;
        endif;
    }
}
