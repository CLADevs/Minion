<?php

namespace CLADevs\Minion\utils;

class Utils{

    //PocketMine original code
    public static function getDirectionFromYaw(float $yaw): ?int{
        $rotation = fmod($yaw - 90, 360);
        if($rotation < 0){
            $rotation += 360.0;
        }
        if((0 <= $rotation and $rotation < 45) or (315 <= $rotation and $rotation < 360)){
            return 2; //North
        }elseif(45 <= $rotation and $rotation < 135){
            return 3; //East
        }elseif(135 <= $rotation and $rotation < 225){
            return 0; //South
        }elseif(225 <= $rotation and $rotation < 315){
            return 1; //West
        }else{
            return null;
        }
    }
}