<?php

return [
    // A core is a word combination. Punctuation, word order and context suffixes
    // never create extra diversity. The allocator enforces these rules again.
    'core_window' => 1000,
    'family_window' => 200,
    'family_days' => 30,
    'similarity_threshold' => 0.88,
    'similarity_min_length' => 8,
    'candidate_batch' => 24,
    'candidate_rounds' => 4,
    'search_budget' => 768,
    'fallback_search_budget' => 512,
    'pattern_weights' => [
        'curated_alias' => 20,
        'modifier_subject' => 35,
        'compound' => 30,
        'activity_subject' => 15,
    ],
];
