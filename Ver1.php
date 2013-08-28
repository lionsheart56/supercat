 <?php


class VersionOne{


/*===============================================*/
/*             Default Constructor               */
/*===============================================*/ 
    public function __construct(){
    }

/*===============================================*/
/*                  Url_init()                   */
/*  Pre-Condition : pass the url which you want  */
/*                  to parse ...                 */
/*  Post-Condition : return the data as String   */
/*                   via create cURL connection  */
/*===============================================*/ 
    public function Url_init($url){
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

/*===============================================*/
/*               remove_Script()                 */
/*  Pre-Condition : pass the url which you want  */
/*                  to remove ...                */
/*  Post-Condition : return the data as String   */
/*                   withouy Script tags         */
/*===============================================*/ 
    public function remove_Script($html){
        $replaceBrs='/(<br[^>]*>[ \n\r\t]*){2,}/i';          

        preg_match("/charset=([\w|\-]+);?/", $html, $match);      
        $charset = isset($match[1]) ? $match[1] : 'UTF-8';
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', $charset);  //Set the encode
        $html = preg_replace($replaceBrs, '</p><p>', $html);        //replace <br> to <p>

        $dom =$this->create_Dom($html);

        $scripts = $dom->getElementsByTagName('script');
        $length = $scripts->length;                             // Find all Script Tags
        for($i=$length-1;$i>=0;$i--){
            $scripts->item($i)->parentNode->removeChild($scripts->item($i));
        }                                                       //Remove Child
        $data = $dom->saveHTML();
        return $data;
    }

/*===============================================*/
/*               create_Dom()                    */
/*  Pre-Condition : pass the url which you want  */
/*                  to build DOM Tree ...        */
/*  Post-Condition : return a DOM tree Variable  */
/*===============================================*/ 
    public function create_Dom($html){
    
        $dom = new DOMDocument();                               // Create a HTMLDOM tree
        $dom->preserveWhiteSpace = false;                       // Remove all whitspace
        $dom->loadHTML($html);                                  // Load this url's DOM tree

        return $dom;
    }

/*===============================================*/
/*                  find_Body()                  */
/*  Pre-Condition : pass the DOM tree Variable   */
/*                                               */
/*  Post-Condition : return a collection of body */
/*                   Tags                        */
/*===============================================*/ 
    public function find_Body($data){
        $dom = $this->create_Dom($data);
        $body = $dom->getElementsByTagName('body');
        if($body == NULL){
        }            // Not to Consider now.

        return $body;
    }

/*===============================================*/
/*                  remove_Style()               */
/*  Pre-Condition : pass the DOM tree Variable   */
/*                                               */
/*  Post-Condition : return a String that remove */
/*                   all style Tags              */
/*===============================================*/ 
    public function remove_Style($data){

        $dom = $this->create_Dom($data);
        $style = $dom->getElementsByTagName('style');
        
        for ($i = $style->length-1; $i >= 0; $i--){
            $style->item($i)->parentNode->removeChild($style->item($i));
        }
        $data = $dom->saveHTML();
        return $data;
    }          


/*===============================================*/
/*                    init()                     */
/*  Pre-Condition : pass the url                 */
/*                                               */
/*  Post-Condition : echo something for test     */
/*                                               */
/*===============================================*/ 
    public function init($url){
   
        $data =$this->Url_init($url);
        $pure_data=$this->remove_Script($data);
        $pure_data=$this->remove_Style($pure_data);
        $pure_data=$this->find_Body($pure_data);

        /*======Just for Test==========*/
        $fp = fopen('output.txt', 'w');
        fwrite($fp,$pure_data);
        fclose($fp);

        $fp = fopen('data.txt','w');
        fwrite($fp,$data);
        fclose($fp);
        echo $data;
        /*=============================*/
        
        return ;
    }

    public function getTitle(){

    }

    public function getContent(){

    }

    public function countScore(){

    }

    public function countLength(){
        
    }
}

    $temp = new VersionOne();
    $temp->init("http://udn.com/NEWS/BREAKINGNEWS/BREAKINGNEWS7/8118338.shtml");


?>