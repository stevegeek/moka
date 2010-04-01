<?php

class _cpObject { }

const Format280NorthMagicNumber = '280NPLIST',
    ARRAY_MARKER        = "A",
    DICTIONARY_MARKER   = "D",
    FLOAT_MARKER        = "f",
    INTEGER_MARKER      = "d",
    STRING_MARKER       = "S",
    TRUE_MARKER         = "T",
    FALSE_MARKER        = "F",
    KEY_MARKER          = "K",
    END_MARKER          = "E";

function parsePropertyListFrom280NorthString(&$obj, &$aString)
{
    while ($aString != "")
    {
        //echo $aString."*\n";
        if (preg_match_all("/^".STRING_MARKER.";([^;]+);([^;]*)(.*)/" ,$aString, $matches))
        {
            $length = intval(implode("", $matches[1]));
            $str = implode("", $matches[2]);

            $value = substr($str, 0, $length);

            echo "string ($length) : $value\n";

            $aString = substr($str, $length) . implode("", $matches[3]);
        }
        else if (preg_match_all("/^".INTEGER_MARKER.";([^;]+);([^;]*)(.*)/" ,$aString, $matches))
        {
            $length = intval(implode("", $matches[1]));
            $str = implode("", $matches[2]);

            $value = intval(substr($str, 0, $length));

            echo "integer ($length) : $value\n";

            $aString = substr($str, $length) . implode("", $matches[3]);
        }
        else if (preg_match_all("/^".FLOAT_MARKER.";([^;]+);([^;]*)(.*)/" ,$aString, $matches))
        {
            $length = intval(implode("", $matches[1]));
            $str = implode("", $matches[2]);

            $value = floatval(substr($str, 0, $length));

            echo "float ($length) : $value\n";

            $aString = substr($str, $length) . implode("", $matches[3]);
        }
        else if (preg_match_all("/^".TRUE_MARKER.";(.*)/" ,$aString, $matches))
        {
            echo "boolean : true\n";

            $aString = implode("", $matches[1]);
        }
        else if (preg_match_all("/^".FALSE_MARKER.";(.*)/" ,$aString, $matches))
        {
            echo "boolean : false\n";

            $aString = implode("", $matches[1]);
        }
        else if (preg_match_all("/^".ARRAY_MARKER.";(.*)/" ,$aString, $matches))
        {
            echo "array : start\n";

            $aString = implode("", $matches[1]);

            parsePropertyListFrom280NorthString($obj, $r);
        }
        else if (preg_match_all("/^".DICTIONARY_MARKER.";(.*)/" ,$aString, $matches))
        {
            echo "dic : start\n";

            $aString = implode("", $matches[1]);

            parsePropertyListFrom280NorthString($obj, $aString);
        }
        else if (preg_match_all("/^".KEY_MARKER.";([^;]+);([^;]*)(.*)/" ,$aString, $matches))
        {
            $length = intval(implode("", $matches[1]));
            $str = implode("", $matches[2]);

            $value = substr($str, 0, $length);

            echo "key ($length) : $value\n";

            $aString = substr($str, $length) . implode("", $matches[3]);

            parsePropertyListFrom280NorthString($obj, $aString);
        }
        else if (preg_match_all("/^".END_MARKER.";(.*)/" ,$aString, $matches))
        {
            echo "     : end\n";

            $aString = implode("", $matches[1]);
            return;
        }
        else if (preg_match_all("/^;(.*)/" ,$aString, $matches))
        {
            // end of string
            $aString = implode("", $matches[1]);
        }
        else
        {
            throw new Exception("INVALID STRING FORMAT");
        }
    }

}

function propertyListFrom280NorthString($aString)
{
    $s = 0;

    if (preg_match_all("/^".Format280NorthMagicNumber.";([^;]+);(.+)/" ,trim($aString), $matches))
    {
        $object = new _cpObject();
        $object->_pListVersion = implode("", $matches[1]);

        echo "Version :".$object->_pListVersion ."\n";
        parsePropertyListFrom280NorthString( $object, implode("", $matches[2]));
    }
    else
    {
        throw new Exception("INVALID");
    }
}

$teststring = '280NPLIST;1.0;D;K;4;$topD;K;4;rootD;K;6;CP$UIDd;1;1E;E;K;8;$objectsA;S;5;$nullS;5;HelloE;K;9;$archiverS;15;CPKeyedArchiverK;8;$versionS;6;100000E;';
//$teststring = '280NPLIST;1.0;S;4;testS;4;test;';
propertyListFrom280NorthString($teststring);
