<?php
	//error_reporting(E_ALL);
 	//ini_set('display_startup_errors', 1);
 	//ini_set('display_errors', 1);

 	require("JSLikeHTMLElement.php");

 	class Reader{
 		
 		public $dom;
 		public $url;
 		public $body=null;
    	public $debug=false;
    	public $Content;
    	public $Title;
    	public $regexps = array(
        	'unlikelyCandidates' => '/comment|community|foot|header|menu|remark|rss|sidebar|sponsor|ad-break|pager|popup/i',
        	'replaceBrs' => '/(<br[^>]*>[ \n\r\t]*){2,}/i',
        	'replaceFonts' => '/<(\/?)font[^>]*>/i',
        	'normalize' => '/\s{2,}/',
            'killBreaks' => '/(<br\s*\/?>(\s|&nbsp;?)*){1,}/',
        	'okMaybeItsACandidate' => '/and|article|body|column|main|shadow/i',
        	'divToPElements' => '/<(a|blockquote|dl|div|img|ol|p|pre|table|ul)/i',
        	'positive' => '/article|body|content|entry|hentry|main|page|pagination|post|text|blog|story/i',
        	'negative' => '/combx|comment|com-|contact|foot|footer|footnote|masthead|media|meta|outbrain|promo|related|scroll|shoutbox|sidebar|sponsor|shopping|tags|tool|widget/i'
    	);

    	function __construct(){

    	}

    	function Curl_init($url){
        	$ch = curl_init();
        	curl_setopt($ch, CURLOPT_URL, $url);
        	curl_setopt($ch, CURLOPT_HEADER, false);
        	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	        $data = curl_exec($ch);

    	    if ($data == false){
        	    $error = curl_error($ch);
           		curl_close($ch);
           	 	return false;
        	}
        	curl_close($ch);
        	return $data;
    	}

    	public function input($url){
    		
    		if(empty($url)) return false;
 
    		$data=$this->Curl_init($url);
    		preg_match("/charset=([\w|\-]+);?/",$data,$match);
    		$charset=isset($match[1]) ? $match[1] : "UTF-8";
    		$data=mb_convert_encoding($data,"HTML-ENTITIES",$charset);

    		unset($this->dom);
    		unset($this->Title);
    		unset($this->Content);
    		$this->url=null;
    		$this->body=null;
    	
    		$data=preg_replace($this->regexps['replaceBrs'], '</p><p>', $data);
			$data=preg_replace($this->regexps['replaceFonts'], '<$1span>', $data);
			$this->dom= new DOMDocument();
			$this->dom->preserveWhiteSpace = false;
			$this->dom->registerNodeClass('DOMElement', 'JSLikeHTMLElement');
			if (trim($data) == '') $data = '<html></html>';
			@$this->dom->loadHTML($data);
			$this->url = $url;
    	}

    	public function init(){

    		$this->removeScript($this->dom);
    		
    		$bodyElements=$this->dom->getElementsByTagName('body');
    		if($bodyElements->length>0){
    			if($this->body==null){
    				$this->body=$bodyElements->item(0);
    			}
    		}

    		if($this->body==null){
    			$this->body=$this->dom->createElement('body');
    			$this->dom->documentElement->appendChild($this->body);
    		}
    		$this->body->setAttribute('id','readerBody');
    		$this->removeStyle($this->dom);

    		$overLay=$this->dom->createElement('div');
    		$innderDiv=$this->dom->createElement('div');
    	    $temp = $this->dom->saveHTML();
        	$Content=$this->FindContent();

    	
    		
    	}

    	private function removeScript($dom){
    		$script=$dom->getElementsByTagName('script');
    		for($i=$script->length-1;$i>=0;$i--){
    			$script->item($i)->parentNode->removeChild($script->item($i));
    		}
    	}

    	private function removeStyle($dom){
    		$style=$dom->getElementsByTagName('style');
    		for($i=$style->length-1;$i>=0;$i--){
    			$style->item($i)->parentNode->removeChild($style->item($i));
    		}
    	}

    	private function getInnerText($w){
    		$textContent="";

    		if(!isset($w->textContent) || $w->textContent==''){
    			return '';
    		}
    		$textContent=trim($w->textContent);
    		return preg_replace($this->regexps['normalize'],' ',$textContent);
    	}

    	private function initializeNode($node){
    		$reader=$this->dom->createAttribute('reader');
    		$reader->value=0;
    		$node->setAttributeNode($reader);
    
    		switch(strtoupper($node->tagName)){
    			case 'DIV':
    				$reader->value+=5;
    				break;

    			case 'PRE':
    			case 'TD':
    			case 'BLOCKQUOTE':
    				$reader->value+=3;
    				break;

    			case 'ADDRESS':
    			case 'OL':
				case 'UL':
				case 'DL':
				case 'DD':
				case 'DT':
				case 'LI':
				case 'FORM':
					$reader->value -= 3;
					break;

				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
				case 'TH':
					$reader->value -= 5;
					break;
    	    }

    	    $reader->value+=$this->ClassWeight($node);
    	}

    	private function ClassWeight($w){
    		$weight=0;
    		if($w->hasAttribute('class') && $w->getAttribute('class') != ''){
    			if(preg_match($this->regexps['negative'], $w->getAttribute('class'))){
    				$weight-=25;
    			}
    			if(preg_match($this->regexps['positive'], $w->getAttribute('class'))){
    				$weight+=25;
    			}
    		}

    		if($w->hasAttribute('id') && $w->getAttribute('id') != ''){
    			if(preg_match($this->regexps['negative'], $w->getAttribute('id'))){
    				$weight-=25;
    			}
    			if(preg_match($this->regexps['positive'], $w->getAttribute('id'))){
    				$weight+=25;
    			}
    		}

    		return $weight;
    	}

        private function LinkDensity($w){
            $link=$w->getElementsByTagName('a');
            $textl=strlen($this->getInnerText($w));
            $linkl=0;
            for($i=0,$il=$link->length; $i<$il ; $i++){
                $linkl+=strlen($this->getInnerText($link->item($i)));
            }
            if($textl>0){
                return $linkl/$textl;
            }
            else
                return 0;
        }

    	private function FindContent(){
    		$page=$this->dom;
    		$all=$page->getElementsByTagName('*');
    		$strip=true;
    		$node=null;
    		$Score=array();
    		for($Index=0;($node=$all->item($Index))&& $Index < $all->length;$Index++){
    			
    			$tagName=strtoupper($node->tagName);
               // echo $tagName."<br/>";
    			if($strip){
    				$unlikely=$node->getAttribute('class').$node->getAttribute('id');
    				if(preg_match($this->regexps['unlikelyCandidates'],$unlikely) &&
    				   !preg_match($this->regexps['okMaybeItsACandidate'], $unlikely) &&
    				   $tagName != 'BODY'){
    					$node->parentNode->removeChild($node);
    					$Index--;
    					continue;
    				}
    			}
    			if($tagName=='P' || $tagName=='TD' || $tagName=="PRE"){
    			
                    $Score[]=$node;
    			}
    			if($tagName=="DIV"){
                  
    				if(!preg_match($this->regexps['divToPElements'], $node->innerHTML)){
    					
                        $newNode=$this->dom->createElement('p');
    					try{
    						$newNode->innerHTML=$node->innerHTML;
    						$node->parentNode->replaceChild($newNode,$node);
    						$Index--;
    						$Score[]=$node;
    					}
    					catch(Exception $w){

    					}
    				}
    				else{
    					for($i=0,$ic=$node->childNodes->length; $i<$ic ;$i++){
    						$childNode=$node->childNodes->item($i);
    						if($childNode->nodeType==3){
    							$p=$this->dom->createElement('p');
    							$p->innerHTML=$childNode->nodeValue;
    							$p->setAttribute('style','display: inline');
    							$p->setAttribute('class','reader-styled');
    							$childNode->parentNode->replaceChild($p,$childNode);
    						}
    					}
    				}
                    
    			}
    		}
    		//echo count($Score);

    		$candidates=array();

    		//BUG
    		for($walk=0;$walk<count($Score);$walk++){
    			//echo $walk."<br/>";
    			$parentNode=$Score[$walk]->parentNode;
    			$grandParentNode= !$parentNode ? null :(($parentNode->parentNode instanceof DOMElement) ? $parentNode->parentNode : null);
    			$innerText=$this->getInnerText($Score[$walk]);
    			//echo $innerText;
    			if(!$parentNode || !isset($parentNode->tagName)){
    				continue;
    			}

    			if(strlen($innerText) <25){
    				continue;
    			}

    			if(!$parentNode->hasAttribute('reader')){
    				$this->initializeNode($parentNode);
    				$candidates[]=$parentNode;
    			}
    			if($grandParentNode && !$grandParentNode->hasAttribute('reader') && isset($grandParentNode->tagName)){
    				$this->initializeNode($grandParentNode);
    				$candidates[]=$grandParentNode;
    			}

    			$contentScore=1;
    			$contentScore+=count(explode(',',$innerText));
    			$contentScore+=min(floor(strlen($innerText)/100),3);
     			$parentNode->getAttributeNode('reader')->value+=$contentScore;
     		
     			if($grandParentNode){
     				$grandParentNode->getAttributeNode('reader')->value+=$contentScore/2;
     			}
     		}
        	

        	$maxvalue=$candidates[0]->getAttributeNode('reader')->value;
            $maxnode=null;
     		for($i=0;$i<count($candidates);$i++){
	            $reader= $candidates[$i]->getAttributeNode('reader');
    	        echo "Each Block Value: ".$reader->value . '<br/>';
                $reader->value*=(1-$this->LinkDensity($candidates[$i]));
                if($reader->value > $maxvalue){
                    $maxvalue=$reader->value;
                    $maxnode=$candidates[$i];
                }
    		}

           
            echo "The most :". $maxvalue . " And its content : <br/>";
            echo $this->getInnerText($maxnode);
 		
            //Looking for Sibling Node
            $Content=$this->dom->createElement('div');
            $Content->setAttribute('id','reader-content');
            $SiblingThreshold=max(10,((int)$maxnode->getAttribute('reader'))*0.2);
            $Sibling=$maxnode->parentNode->childNodes;

            if(!isset($Sibling)){
                $Sibling=new stdClass;
                $Sibling->length=0; 
            }

            for($i=0,$sl=$Sibling->length;$i<$sl;$i++){
                $sibNode=$Sibling->item($i);
                $append=0;

                if($sibNode==$maxnode){
                    $append=1;
                }

                $contentBonus = 0;
                if($sibNode->nodeType===XML_ELEMENT_NODE && $sibNode->getAttribute('class')==$maxnode->getAttribute('class') && $maxnode->getAttribute('class')!=' '){
                    $contentBonus +=((int)$maxnode->getAttribute('reader'))*0.2;
                }
                if($sibNode->nodeType===XML_ELEMENT_NODE && $sibNode->hasAttribute('reader') && (((int)$sibNode->getAttribute('reader'))+$contentBonus)>= $SiblingThreshold){
                    $append=1;
                }
                if(strtoupper($sibNode->nodeName)=='P'){
                    $linkDensity =$this->LinkDensity($sibNode);
                    $nodeContent =$this->getInnerText($sibNode);
                    $nodeLength = strlen($nodeContent);
                    if($nodeLength > 80 && $linkDensity < 0.25){
                        $append=1;
                    }
                    else if($nodeLength < 80 && $linkDensity === 0 && preg_match('/\.( |$)/', $nodeContent)){
                        $append=1;
                    }
                }
                if($append==1){
                    $nodeToAppend = null;
                    $sibNodeName = strtoupper($sibNode->nodeName);
                    if($sibNodeName != 'DIV' && $sibNodeName != 'P'){
                        $nodeToAppend= $this->dom->createElement('DIV');
                        try{
                            $nodeToAppend->setAttribute('id',$sibNode->getAttribute('id'));
                            $nodeToAppend->innerHTML=$sibNode->innerHTML;
                        }
                        catch(Exception $e){
                            $nodeToAppend = $sibNode;
                            $i--;
                            $sl--;
                        }
                    }
                    else{
                        $nodeToAppend=$sibNode;
                        $i--;
                        $sl--;
                    }

                    $nodeToAppend->removeAttribute('class');
                    $Content->appendChild($nodeToAppend);
                }
            }
          // $Content->innerHTML;
           // $this->ContentClean($Content);
            $doc=$this->getInnerText($Content);
            echo $doc;
            return $Content;     
        }

        private function ContentClean($node){
            $this->CleanStyle($node);
            $this->CleanBreak($node);
            $this->CleanSomething($node,'form');
            $this->Clean($node,'object');
            $this->Clean($node,'h1');

            if($node->getElementsByTagName('h2')->length==1){
                $this->Clean($node,'h2');
            }
            $this->Clean($node,'iframe');
            $this->CleanHeader($node);
            $this->CleanSomething($node,'table');
            $this->CleanSomething($node,'ul');
            $this->CleanSomething($node,'div');

            $para=$node->getElementsByTagName('p');
            for($i=$para->length-1;$i>=0;$i--){
                $img=$para->item($i)->getElementsByTagName('img')->length;
                $embed=$pare->item($i)->getElementsByTagName('embed')->length;
                $object=$para->item($i)->getElementsByTagName('object')->length;

                if($img===0 && $embed===0 && $object===0 && $this->getInnerText($para->item($i),false)==''){
                    $para->item($i)->parentNode->removeChild($para->item($i));
                }
            }

            try{
                $node->innerHTML=preg_replace('/<br[^>]*>\s*<p/i','<p',$node->innerHTML);
            }
            catch(Exception $e){

            }
        }

        private function CleanHeader($w){
            for($index=1;$index<3;$index++){
                $headers=$w->getElementsByTagName('h'.$index);
                for($i=$headers->length-1;$i>=0;$i--){
                    if($this->ClassWeight($headers->item($i))<0 || $this->LinkDensity($headers->item($i))>0.33){
                        $headers->item($i)->parentNode->removeChild($headers->item($i));
                    }
                }
            }
        }

        private function Clean($w,$tag){
            $tagList=$w->getElementsByTagName($tag);
            $isEmbed = ($tag=='object' || $tag=='embed');

            for($i=$tagList->length-1;$i>=0;$i--){
                if($isEmbed){
                    $attValue='';
                    for($j=0,$k=$tagList->item($i)->attributes->length;$j<$k;$j++){
                        $attValue .=$tagList->item($i)->attributes->item($i)->value . '|';
                    }

                    if(preg_match($this->regexps['video'], $attValue)){
                        continue;
                    }
                    if(preg_match($this->regexps['video'], $tagList->item($i)->innerHTML)){
                        continue;
                    }
                }
                $tagList->item($i)->parentNode->removeChild($tagList->item($i));
            }

        }

        private function CleanSomething($w,$tag){

            $tagList=$w->getElementsByTagName($tag);
            $Length=$tagList->length;

            for($i=$Length-1;$i>=0;$i--){
                $weight=$this->ClassWeight($tagList->item($i));
                $contentScore = ($tagList->item($i)->hasAttribute('reader')) ? (int)$tagList->item($i)->getAttribute('reader') :0;
            }

        }
        private function CleanStyle($w){
            if(!is_object($w)) return ;
            $e=$w->getElementsByTagName('*');

            foreach($e as $ex){
                $ex->removeAttribute('style');
            }
        }

        private function CleanBreak($w){
            $html=$w->innerHTML;
            $html=preg_replace($this->regexps['killBreaks'],'<br/>',$html);
            $w->innerHTML=$html;
        }

 	}
    $url=$_POST["url"];
 	$doc=new Reader();
 	$doc->input($url);
	$doc->init();


?>