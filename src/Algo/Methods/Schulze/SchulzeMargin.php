<?php
/*
    Part of SCHULZE method Module - From the original Condorcet PHP

    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);

namespace CondorcetPHP\Condorcet\Algo\Methods\Schulze;

use CondorcetPHP\Condorcet\Dev\CondorcetDocumentationGenerator\CondorcetDocAttributes\{Description, Example, FunctionReturn, PublicAPI, Related};
use CondorcetPHP\Condorcet\Election;

class SchulzeMargin extends Schulze_Core
{
    // Method Name
    public const METHOD_NAME = ['Schulze Margin', 'SchulzeMargin', 'Schulze_Margin'];

    protected function schulzeVariant(int $i, int $j, Election $election): int
    {
        return $election->getPairwise()[$i]['win'][$j] - $election->getPairwise()[$j]['win'][$i];
    }
}
