<?php

use App\Support\Text\PersianTextNormalizer;

beforeEach(function () {
    $this->normalizer = new PersianTextNormalizer;
});

it('returns null and empty untouched', function () {
    expect($this->normalizer->clean(null))->toBeNull()
        ->and($this->normalizer->clean(''))->toBe('');
});

it('extracts CDATA payload keeping inner html (TCK parity)', function () {
    // TCK quirk: tags are stripped only OUTSIDE CDATA; the projection
    // layer handles markup inside CDATA content downstream.
    $raw = '<![CDATA[<p>موضوع جلسه</p>]]>';

    expect($this->normalizer->clean($raw))->toBe('<p>موضوع جلسه</p>');
});

it('falls back to raw text when CDATA is unterminated', function () {
    $raw = '<![CDATA[متن ناقص';

    // TCK parity: no match → keep text as-is (strip_tags on it).
    expect($this->normalizer->clean($raw))->toBe($raw);
});

it('strips html tags when no CDATA', function () {
    $raw = '<p>متن <b>نامه</b></p>';

    expect($this->normalizer->clean($raw))->toBe('متن نامه');
});

it('decodes html entities', function () {
    expect($this->normalizer->clean('&quot;مصوبه&quot; &amp; ضمیمه'))->toBe('"مصوبه" & ضمیمه');
});

it('folds arabic letters to persian', function () {
    // ي ك ة → ی ک ه ; أ إ → ا ; ؤ → و ; hamza ء dropped everywhere (TCK parity)
    expect($this->normalizer->clean('يوسف كتب ةقية أصل إسلام ؤلاء'))->toBe('یوسف کتب هقیه اصل اسلام ولا');
});

it('removes tatweel and hamza', function () {
    expect($this->normalizer->clean('مـتـنــًا'))->not->toContain('ـ')
        ->and($this->normalizer->clean('جزء'))->toBe('جز');
});

it('collapses whitespace and invisible characters', function () {
    $raw = "متن\u{00A0}\u{200B}  دوم\t\nسوم";

    expect($this->normalizer->clean($raw))->toBe('متن دوم سوم');
});

it('fixes punctuation spacing', function () {
    expect($this->normalizer->clean('مصوبه ، شد ؛ و تمام .'))->toBe('مصوبه، شد؛ و تمام.')
        // TCK parity: the space rule also covers brackets, so a space
        // before an opening bracket is removed too.
        ->and($this->normalizer->clean('( متن ) و [ متن ]'))->toBe('(متن) و[متن]');
});

it('trims result', function () {
    expect($this->normalizer->clean('   متن با فاصله   '))->toBe('متن با فاصله');
});
