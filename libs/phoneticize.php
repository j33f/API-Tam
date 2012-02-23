<?
/*
Author : jean-François VIAL <http://about.me/Jeff_>
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>

Fork it on GitHub ! https://github.com/Modulaweb/Phonetic-comparison-tool-for-french
*/

function cleanStr($str) {
    return trim(
            strtolower(
            preg_replace('/ {2,}/',' ',
            preg_replace('/[^ \w]/u',' ',
            preg_replace('/[ÀÁÂÃÄÅÆàáâãäå]/u','a',
            preg_replace('/[æÆ]/u','ae',
            preg_replace('/[Çç]/u','c',
            preg_replace('/[ÈÉÊËèéêë]/u','e',
            preg_replace('/[ÌÍÎÏìíîï]/u','i',
            preg_replace('/[Ð]/u','d',
            preg_replace('/[Ññ]/u','n',
            preg_replace('/[ÒÓÔÕÖØðñòóôõöø]/u','o',
            preg_replace('/[œŒ]/u','oe',
            preg_replace('/[ÙÚÛÜùúûüµ]/u','u',
            preg_replace('/[Ýýÿ]/u','y',
            preg_replace('/[ß]/u','ss',
            str_replace(' I ',' 1 ',
            str_replace(' II ',' 2 ',
            str_replace(' III ',' 3 ',
            str_replace(' IV ',' 4 ',
            str_replace(' V ',' 5 ',
            str_replace(' VI ',' 6 ',
            str_replace(' VII ',' 7 ',
            str_replace(' VIII ',' 8 ',
            str_replace(' IX ',' 9 ',
            str_replace(' X ',' 10 ',
            str_replace(' XI ',' 11 ',
            str_replace(' XII ',' 12 ',
            str_replace(' XIII ',' 13 ',
            str_replace(' XIV ',' 14 ',
            str_replace(' XV ',' 15 ',
            str_replace(' XVI ',' 16 ',
            str_replace(' XVII ',' 17 ',
            str_replace(' XVIII ',' 18 ',
            str_replace(' XIX ',' 19 ',
            str_replace(' XX ',' 20 ',
            str_replace(' XXI ',' 21 ',
            str_replace(' XXII ',' 22 ',
            str_replace(' XXIII ',' 23 ',
            str_replace(' XXIV ',' 24 ',
            $str
            ))))))))))))))))))))))))))))))))))))))));
}
function unduplicateLetters($str) {
    // makes "heeeellllooo theere" to "helo there"
    $final = '';
    $tmp = explode("\n",chunk_split($str,1,"\n"));
    foreach($tmp as $k=>$v){
        if ($k==0) {
            $final=$v;
        } else {
            if ($final[strlen($final)-1] != $v)
                $final.=$v;
        }
    }
    return $final;
}
function phoneticize($str) {
    // creates a french phonetic string allowing to guess that "sottereleu" (misspelled) is similar to "sauterelle" (correctly spelled)
    // 1) clean the string making all chars only letters, numbers and spaces
    // 2) remove duplicate letters
    // 3) replace some similar phonems by an univoque one
    // factorizing the words that way allow to compare 2 words and find if they sounds the same or not.
    // it is more accurate than any soundex functions since it not based on differences of raw words
    // it is more faster and less greedy
    return  str_replace(' ','',
            str_replace(array('eu','eux','eut'),'e',
            str_replace(array('eau','au'),'o',
            str_replace(array('ais ', 'ait '),'e ',
            preg_replace('/e[rt] /','e ',
            preg_replace('/([aeiouy]s[tm]) /','$1e ',
            preg_replace('/([^aeiouy])h/','$1',
            str_replace(array('eu','oe'),'e',
            str_replace(array(' eu ',' eus ',' eut ',' ut '),' u ',
            preg_replace('/([aeiouy])m([pb])/','$1n$2',
            preg_replace('/([^aeiouys])[ts] /','$1 ',
            str_replace('ae','e',
            str_replace('au','o',
            str_replace('qu','k',
            str_replace('ci','si',
            str_replace(array('ai','ay', 'ei','ey'),'e',
            str_replace(array('ein ','ain '),'in ',
            str_replace('an','en',
            str_replace(array('rs ','rts'),'r ',
            str_replace('tp','p',
            str_replace('ies ','i ',
            str_replace('oie ','oi ',
            str_replace('ue ','u ',
            str_replace('y','i',
            str_replace('rt','r',
            preg_replace('/([aiouy])se? /','$1sse ',
            preg_replace('/([^aeouy])ie /','$1i ',
            str_replace('kk','k',
            preg_replace('/c([aeoiuyk])/','k$1',
            str_replace('ce','sse',
            str_replace(array('leu ','leux ', 'leut'),'le ',
            preg_replace('/h([aeoiuy])/','$1',
            preg_replace('/([aeoiuy])h/','$1',
            str_replace('aint','int',
            str_replace('ch','§',
            str_replace('ph','f',
            str_replace('th','t',
            str_replace(' dr ',' docteur ',
            str_replace(' av ',' avenue ',
            str_replace(' pl ',' place ',
            str_replace(' dla ',' de la ',
            str_replace(' d la ',' de la ',
            str_replace(' des ',' dez ',
            str_replace(' st ',' saint ',
            ' '.unduplicateLetters(cleanStr($str)).' '
            ))))))))))))))))))))))))))))))))))))))))))));
}
?>
