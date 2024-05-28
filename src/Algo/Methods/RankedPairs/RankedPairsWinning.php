<?php
/*
    Part of RANKED PAIRS method Module - From the original Condorcet PHP

    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/

declare(strict_types=1);

namespace CondorcetPHP\Condorcet\Algo\Methods\RankedPairs;

// Ranked Pairs is a Condorcet Algorithm | http://en.wikipedia.org/wiki/Ranked_Pairs
class RankedPairsWinning extends RankedPairs_Core
{
    // Method Name
    public const array METHOD_NAME = ['Ranked Pairs Winning', 'RankedPairsWinning', 'Tideman Winning', 'RP Winning'];

    protected const string RP_VARIANT_1 = 'win';
}
