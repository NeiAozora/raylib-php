<?php
use raylib\Draw;
use raylib\Text;
use raylib\Timming;
use raylib\Window;


// Initialization
//--------------------------------------------------------------------------------------
$screenWidth = 800;
$screenHeight = 450;

Window::init($screenWidth, $screenHeight, "raylib [core] example - keyboard input");

$ballPosition = ['x' => $screenWidth / 2, 'y' => $screenHeight / 2];

Timming::setTargetFps(60);       // Set target frames-per-second
//--------------------------------------------------------------------------------------

// Main game loop
while (!Window::shouldClose())    // Detect window close button or ESC key
{
    // Update
    //----------------------------------------------------------------------------------
    if (raylib\IsKeyDown(raylib\keys\RIGHT)) { $ballPosition['x'] += 0.8; }
    if (raylib\IsKeyDown(raylib\keys\LEFT)) { $ballPosition['x'] -= 0.8; }
    if (raylib\IsKeyDown(raylib\keys\UP)) { $ballPosition['y'] -= 0.8; }
    if (raylib\IsKeyDown(raylib\keys\DOWN)) { $ballPosition['y'] += 0.8; }
    //----------------------------------------------------------------------------------

    // Draw
    //----------------------------------------------------------------------------------
    Draw::begin();

    Draw::clearBackground([245, 245, 245, 255]);

    Text::draw("move the ball with arrow keys", 10, 10, 20, [130, 130, 130, 255]);

    Draw::circleV($ballPosition, 50, [190, 33, 55, 255]);

    Draw::end();
    //----------------------------------------------------------------------------------
}

// De-Initialization
//--------------------------------------------------------------------------------------
Window::close();        // Close window and OpenGL context
//--------------------------------------------------------------------------------------
