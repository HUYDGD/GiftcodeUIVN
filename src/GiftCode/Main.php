<?php

#=========================================================================================================================#

namespace GiftCode;

#=========================================================================================================================#

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;

#=========================================================================================================================#

use pocketmine\event\player\PlayerQuitEvent;

#=========================================================================================================================#

use GiftCode\FormEvent\Form;
use GiftCode\FormEvent\CustomForm;

#=========================================================================================================================#

use onebone\economyapi\EconomyAPI;

#=========================================================================================================================#

class Main extends PluginBase implements Listener {

#=========================================================================================================================#

    public $used;
    public $eco;
    public $giftcode;
    public $instance;
    public $formCount = 0;
    public $forms = [];

#=========================================================================================================================#

    public function onEnable() {
     $this->getLogger()->info("Plugin Giftcode làm lại bởi ZulfahmiFjr");
	 $this->getLogger()->info("§aGiftcode[việt hóa] v1 đã được bật!");
	 $this->getLogger()->info("§aPlugin được dịch bởi Sói");
     $plugin = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
     if(is_null($plugin)) {
      $this->getLogger()->info("Vui lòng cài đặt plugin EconomyAPI!");
     $this->getServer()->shutdown();
     }else{
      $this->eco = EconomyAPI::getInstance();
     }
     $this->formCount = rand(0, 0xFFFFFFFF);
     $this->getServer()->getPluginManager()->registerEvents($this, $this);
     if(!is_dir($this->getDataFolder())) {
      mkdir($this->getDataFolder());
     }
     $this->used = new \SQLite3($this->getDataFolder() ."used-code.db");
     $this->used->exec("CREATE TABLE IF NOT EXISTS code (code);");
     $this->giftcode = new \SQLite3($this->getDataFolder() ."code.dn");
     $this->giftcode->exec("CREATE TABLE IF NOT EXISTS code (code);");
     }

#=========================================================================================================================#

    public function createCustomForm(callable $function = null) : CustomForm {
     $this->formCountBump();
     $form = new CustomForm($this->formCount, $function);
     $this->forms[$this->formCount] = $form;
     return $form;
    }

#=========================================================================================================================#

    public function formCountBump() : void {
     ++$this->formCount;
     if($this->formCount & (1 << 32)){
      $this->formCount = rand(0, 0xFFFFFFFF);
     }
  }

#=========================================================================================================================#

    public function onPacketReceived(DataPacketReceiveEvent $ev) : void {
     $pk = $ev->getPacket();
     if($pk instanceof ModalFormResponsePacket){
      $player = $ev->getPlayer();
      $formId = $pk->formId;
      $data = json_decode($pk->formData, true);
      if(isset($this->forms[$formId])){
       $form = $this->forms[$formId];
       if(!$form->isRecipient($player)){
        return;
       }
       $callable = $form->getCallable();
       if(!is_array($data)){
        $data = [$data];
       }
       if($callable !== null) {
        $callable($ev->getPlayer(), $data);
       }
       unset($this->forms[$formId]);
       $ev->setCancelled();
       }
    }
 }

#=========================================================================================================================#

    public function onPlayerQuit(PlayerQuitEvent $ev) {
     $player = $ev->getPlayer();
     foreach ($this->forms as $id => $form) {
      if($form->isRecipient($player)) {
       unset($this->forms[$id]);
       break;
      }
   }
}

#=========================================================================================================================#

    public function RedeemMenu($player){
     if($player instanceof Player){
      $form = $this->createCustomForm(function(Player $player, array $data){
      $result = $data[0];
      if($result != null){
       if($this->codeExists($this->giftcode, $result)) {
        if(!($this->codeExists($this->used, $result))) {
         $chance = mt_rand(1, 5);
         $this->addCode($this->used, $result);
         switch($chance) {         default:
          $player->sendMessage("§f§l[§r§eGiftCode§r§f§l]§r§6§o Bạn đã đổi giftcode thành công và nhận được quà tặng là§r§f 20.000$");
          $this->eco->addMoney($player->getName(), 20000);
          break;
        }
     }else{
       $player->sendMessage("§f§l[§r§eGiftCode§r§f§l]§r§6§o Giftcode này đã được sử dụng, hãy nhập mã quà khác§r§f!");
        return true;
       }
    }else{
      $player->sendMessage("§f§l[§r§eGiftCode§r§f§l]§r§6§o Không tìm thấy mã quà tặng, rất tiếc§r§f!");
      return true;
     }
  }else{
    $player->sendMessage("§f§l[§r§eGiftCode§r§f§l]§r§6§o Bạn đã không nhập code§r§f!");
    return true;
   }
});
$form->setTitle("§r§f§l-=§eMenu Đổi Giftcode§r§l§f=-");
$form->addInput("§6§oNhập mã bạn muốn đổi vào cột bên dưới§r§f!");
$form->sendToPlayer($player);
}
}

#=========================================================================================================================#

    public static function getInstance() {
     return $this;
    }

#=========================================================================================================================#

    public function generateCode() {
     $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
     $charactersLength = strlen($characters);
     $length = 10;
     $randomString = 'CODE';
     for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
     }
     $this->addCode($this->giftcode, $randomString);
     return $randomString;
     }

#=========================================================================================================================#

     public function codeExists($file, $code) {
      $query = $file->query("SELECT * FROM code WHERE code='$code';");
      $ar = $query->fetchArray(SQLITE3_ASSOC);
      if(!empty($ar)) {
       return true;
      } else {         return false;
        }
     }

#=========================================================================================================================#

    public function addCode($file, $code) {
     $stmt = $file->prepare("INSERT OR REPLACE INTO code (code) VALUES (:code);");
     $stmt->bindValue(":code", $code);
     $stmt->execute();
    }

#=========================================================================================================================#

    public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool{
     switch($command->getName()){
      case "taocode";
       if($player->isOp()) {
        $code = $this->generateCode();
        $player->sendMessage ("§f§l[§r§eGiftCode§r§f§l]§r§6§o Tạo thành công! Mã là§r§f: " . $code);
       }else{
         $player->sendMessage ("§f§l[§r§eGiftCode§r§f§l]§r§6§o Xin lỗi nhưng mà bạn không phải là OP§r§f!");
        }
        break;      case "redeem";
       if($player instanceof Player){
        $this->RedeemMenu($player);
       }else{
         $player->sendMessage("§f§l[§r§eGiftCode§r§f§l]§r§6§o Vui lòng sử dụng lệnh này trong trò chơi§r§f!");
        }
     }
     return true;
     }
  }

#=========================================================================================================================#