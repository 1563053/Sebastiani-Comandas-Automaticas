<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sebatiani POS - Modern Artisan</title>
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="src/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-screen w-screen overflow-hidden flex text-sm lg:text-base">
    <aside class="w-24 bg-white border-r border-gray-200 flex flex-col items-center py-6 z-20 shadow-sm shrink-0">
        <div class="w-14 h-14 bg-[#A83232] rounded-2xl flex items-center justify-center text-white text-2xl font-bold mb-10 shadow-lg cursor-pointer hover:scale-105 transition-transform">
            S
        </div>

        <nav class="flex-1 flex flex-col gap-6 w-full px-4">
            <button class="w-full aspect-square rounded-2xl bg-[#A83232] text-white flex flex-col items-center justify-center gap-1 shadow-md transition-all">
                <i class="fa-solid fa-cash-register text-xl"></i>
                <span class="text-[10px] font-head font-bold">POS</span>
            </button>
            
            <button class="w-full aspect-square rounded-2xl text-gray-400 hover:bg-[#F9F7F1] hover:text-[#A83232] flex flex-col items-center justify-center gap-1 transition-all">
                <i class="fa-solid fa-chair text-xl"></i>
                <span class="text-[10px] font-head font-bold">Mesas</span>
            </button>

            <button class="w-full aspect-square rounded-2xl text-gray-400 hover:bg-[#F9F7F1] hover:text-[#A83232] flex flex-col items-center justify-center gap-1 transition-all">
                <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                <span class="text-[10px] font-head font-bold">Historial</span>
            </button>
        </nav>

        <button class="w-12 h-12 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition-colors mt-auto">
            <i class="fa-solid fa-gear text-xl"></i>
        </button>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="px-8 py-6 flex justify-between items-center bg-[#F9F7F1] shrink-0">
            <div class="flex flex-col">
                <h1 class="text-2xl font-head font-extrabold text-[#2C2C2C]">Hola, Carlos 👋</h1>
                <p class="text-gray-500 text-sm font-semibold">Turno Mañana • <span class="text-[#556B2F]">Online</span></p>
            </div>

            <div class="relative w-96 mx-8">
                <input type="text" placeholder="Buscar plato, ingrediente..." 
                       class="w-full pl-12 pr-4 py-3 bg-white border-none rounded-full shadow-sm text-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>

            <div class="flex gap-3">
                <button class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                    <i class="fa-solid fa-bell"></i>
                </button>
                <button class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                    <i class="fa-solid fa-wifi"></i>
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-8 pb-8 custom-scrollbar">
            <section class="mb-10">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-head font-bold text-[#2C2C2C]">Estado del Salón</h2>
                    <button class="text-[#A83232] font-bold text-sm hover:underline">Ver mapa completo <i class="fa-solid fa-arrow-right ml-1"></i></button>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-4">
                    <div class="bg-white p-4 rounded-[20px] shadow-sm border-2 border-dashed border-gray-200 hover:border-[#556B2F] cursor-pointer transition-all group">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-head font-bold text-gray-400 group-hover:text-[#556B2F]">M-01</span>
                            <i class="fa-regular fa-circle text-gray-300 group-hover:text-[#556B2F]"></i>
                        </div>
                        <div class="flex flex-col items-center justify-center py-2 text-gray-400">
                            <i class="fa-solid fa-chair text-2xl mb-1 group-hover:scale-110 transition-transform"></i>
                            <span class="text-xs font-bold">Libre</span>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-[20px] shadow-md border-l-4 border-[#A83232] cursor-pointer relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-12 h-12 bg-[#A83232]/10 rounded-full"></div>
                        <div class="flex justify-between items-start mb-2 relative z-10">
                            <span class="font-head font-bold text-[#2C2C2C] text-lg">M-04</span>
                            <span class="bg-[#A83232] text-white text-[10px] font-bold px-2 py-0.5 rounded-full">Ocupada</span>
                        </div>
                        <div class="text-xs text-gray-500 mb-1"><i class="fa-solid fa-users mr-1"></i> 4 Pax</div>
                        <div class="text-sm font-bold text-[#A83232]">$45.00</div>
                        <div class="text-[10px] text-gray-400 mt-1">12m transcurridos</div>
                    </div>

                    <div class="bg-[#FFFBF0] p-4 rounded-[20px] shadow-md border border-[#D4A017] cursor-pointer relative">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-head font-bold text-[#2C2C2C] text-lg">M-08</span>
                            <i class="fa-solid fa-receipt text-[#D4A017] animate-pulse"></i>
                        </div>
                        <div class="text-xs text-gray-600 mb-1">Esperando cuenta</div>
                        <button class="w-full mt-2 bg-[#D4A017] text-white text-xs font-bold py-1.5 rounded-full hover:bg-[#b88b14]">
                            Cobrar
                        </button>
                    </div>

                    <div class="bg-gray-100 p-4 rounded-[20px] shadow-inner opacity-80 cursor-not-allowed">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-head font-bold text-gray-500">M-02</span>
                            <i class="fa-solid fa-clock text-gray-400"></i>
                        </div>
                        <div class="flex flex-col items-center py-2 text-gray-400">
                            <span class="text-xs font-bold uppercase tracking-wider">Reservada</span>
                            <span class="text-[10px]">19:00 PM</span>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <div class="flex gap-3 mb-6 overflow-x-auto pb-2 scrollbar-hide">
                    <button class="btn-pill px-6 py-2.5 bg-[#2C2C2C] text-white font-head font-bold shadow-lg hover:-translate-y-0.5 transition-all shrink-0">
                        <i class="fa-solid fa-utensils mr-2"></i>Todo
                    </button>
                    <button class="btn-pill px-6 py-2.5 bg-white text-[#2C2C2C] font-head font-bold shadow-sm border border-gray-100 hover:bg-[#A83232] hover:text-white transition-all shrink-0">
                        <i class="fa-solid fa-pizza-slice mr-2"></i>Pizzas
                    </button>
                    <button class="btn-pill px-6 py-2.5 bg-white text-[#2C2C2C] font-head font-bold shadow-sm border border-gray-100 hover:bg-[#A83232] hover:text-white transition-all shrink-0">
                        <i class="fa-solid fa-bowl-food mr-2"></i>Pastas
                    </button>
                    <button class="btn-pill px-6 py-2.5 bg-white text-[#2C2C2C] font-head font-bold shadow-sm border border-gray-100 hover:bg-[#A83232] hover:text-white transition-all shrink-0">
                        <i class="fa-solid fa-wine-glass mr-2"></i>Vinos
                    </button>
                    <button class="btn-pill px-6 py-2.5 bg-white text-[#2C2C2C] font-head font-bold shadow-sm border border-gray-100 hover:bg-[#A83232] hover:text-white transition-all shrink-0">
                        <i class="fa-solid fa-ice-cream mr-2"></i>Postres
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div class="card-modern bg-white p-4 relative group cursor-pointer hover:shadow-soft transition-all duration-300 hover:-translate-y-1">
                        <div class="w-full h-32 rounded-2xl bg-gray-100 overflow-hidden mb-3 relative">
                            <img src="https://images.unsplash.com/photo-1574071318508-1cdbab80d002?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm p-1.5 rounded-full shadow-sm text-[#A83232]">
                                <i class="fa-solid fa-heart text-xs"></i>
                            </div>
                        </div>
                        <h3 class="font-head font-bold text-[#2C2C2C] text-lg leading-tight mb-1">Pizza Caprese</h3>
                        <p class="text-xs text-gray-400 mb-3 truncate">Tomate, mozzarella, pesto genovés.</p>
                        <div class="flex justify-between items-center">
                            <span class="font-head font-extrabold text-[#A83232] text-lg">$16.00</span>
                            <button class="w-8 h-8 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="card-modern bg-white p-4 relative group cursor-pointer hover:shadow-soft transition-all duration-300 hover:-translate-y-1">
                        <div class="w-full h-32 rounded-2xl bg-gray-100 overflow-hidden mb-3">
                            <img src="https://images.unsplash.com/photo-1621996346529-1f03f57ed595?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover">
                        </div>
                        <h3 class="font-head font-bold text-[#2C2C2C] text-lg leading-tight mb-1">Ravioli Tartufo</h3>
                        <p class="text-xs text-gray-400 mb-3 truncate">Crema de trufa negra y setas.</p>
                        <div class="flex justify-between items-center">
                            <span class="font-head font-extrabold text-[#A83232] text-lg">$24.00</span>
                            <button class="w-8 h-8 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="card-modern bg-gray-50 p-4 relative group opacity-60 grayscale">
                        <div class="absolute inset-0 flex items-center justify-center z-10">
                            <span class="bg-gray-800 text-white text-xs font-bold px-3 py-1 rounded-full">AGOTADO</span>
                        </div>
                        <div class="w-full h-32 rounded-2xl bg-gray-200 overflow-hidden mb-3">
                            <img src="https://images.unsplash.com/photo-1551024601-bec78aea704b?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover">
                        </div>
                        <h3 class="font-head font-bold text-[#2C2C2C] text-lg leading-tight mb-1">Lasagna Boloñesa</h3>
                        <div class="flex justify-between items-center mt-6">
                            <span class="font-head font-extrabold text-gray-400 text-lg">$18.00</span>
                        </div>
                    </div>

                     <div class="card-modern bg-white p-4 relative group cursor-pointer hover:shadow-soft transition-all duration-300 hover:-translate-y-1">
                        <div class="w-full h-32 rounded-2xl bg-gray-100 overflow-hidden mb-3">
                            <img src="https://images.unsplash.com/photo-1563379926898-05f4575a45d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover">
                        </div>
                        <h3 class="font-head font-bold text-[#2C2C2C] text-lg leading-tight mb-1">Carbonara</h3>
                        <p class="text-xs text-gray-400 mb-3 truncate">Guanciale, huevo, pecorino.</p>
                        <div class="flex justify-between items-center">
                            <span class="font-head font-extrabold text-[#A83232] text-lg">$19.50</span>
                            <button class="w-8 h-8 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <aside class="w-100 bg-white shadow-2xl z-30 flex flex-col h-full border-l border-gray-100 relative">
        <div class="p-6 border-b border-gray-100 bg-white">
            <div class="flex justify-between items-center mb-1">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-[#556B2F] animate-pulse"></div>
                    <span class="font-head font-bold text-lg text-[#2C2C2C]">Mesa 04</span>
                </div>
                <span class="text-xs text-gray-400 font-bold bg-gray-100 px-2 py-1 rounded-lg">#ORD-8922</span>
            </div>
            <div class="flex gap-2 text-xs text-gray-500">
                <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> Carlos M.</span>
                <span>•</span>
                <span class="flex items-center"><i class="fa-solid fa-clock mr-1"></i> 12:45 PM</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <div class="flex gap-3 items-start group hover:bg-gray-50 p-2 rounded-xl transition-colors">
                <div class="w-6 h-6 rounded bg-[#A83232]/10 text-[#A83232] flex items-center justify-center font-bold text-xs mt-1">1</div>
                <div class="flex-1">
                    <div class="flex justify-between">
                        <span class="font-head font-bold text-gray-800">Pizza Caprese</span>
                        <span class="font-bold text-gray-800">$16.00</span>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">Sin cebolla</div>
                </div>
            </div>

            <div class="flex gap-3 items-start group hover:bg-gray-50 p-2 rounded-xl transition-colors">
                <div class="w-6 h-6 rounded bg-[#A83232]/10 text-[#A83232] flex items-center justify-center font-bold text-xs mt-1">2</div>
                <div class="flex-1">
                    <div class="flex justify-between">
                        <span class="font-head font-bold text-gray-800">Ravioli Tartufo</span>
                        <span class="font-bold text-gray-800">$48.00</span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <span class="text-[10px] bg-[#D4A017]/20 text-[#D4A017] px-1.5 py-0.5 rounded font-bold">+ Extra Queso</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 items-start group hover:bg-gray-50 p-2 rounded-xl transition-colors">
                <div class="w-6 h-6 rounded bg-[#A83232]/10 text-[#A83232] flex items-center justify-center font-bold text-xs mt-1">1</div>
                <div class="flex-1">
                    <div class="flex justify-between">
                        <span class="font-head font-bold text-gray-800">Coca Cola Zero</span>
                        <span class="font-bold text-gray-800">$3.50</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 bg-[#F9F7F1] border-t border-gray-200 rounded-t-[30px] shadow-[0_-5px_15px_rgba(0,0,0,0.02)]">
            <div class="space-y-2 mb-6 text-sm">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal</span>
                    <span>$67.50</span>
                </div>
                <div class="flex justify-between text-gray-500">
                    <span>Impuestos (10%)</span>
                    <span>$6.75</span>
                </div>
                <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200">
                    <span class="font-head font-extrabold text-xl text-[#2C2C2C]">Total</span>
                    <span class="font-head font-extrabold text-2xl text-[#A83232]">$74.25</span>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2 mb-3">
                <button class="col-span-1 py-3 rounded-2xl border border-[#A83232] text-[#A83232] hover:bg-[#A83232]/10 font-bold transition-colors flex flex-col items-center justify-center gap-1 text-xs">
                    <i class="fa-solid fa-print"></i>
                    <span>Pre-Cta</span>
                </button>
                <button class="col-span-1 py-3 rounded-2xl border border-[#556B2F] text-[#556B2F] hover:bg-[#556B2F]/10 font-bold transition-colors flex flex-col items-center justify-center gap-1 text-xs">
                    <i class="fa-solid fa-note-sticky"></i>
                    <span>Nota</span>
                </button>
                <button class="col-span-2 py-3 rounded-2xl bg-[#556B2F] text-white hover:bg-[#435525] font-bold shadow-md transition-transform active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    MARCHAR
                </button>
            </div>

            <button class="w-full py-4 rounded-full bg-[#A83232] text-white font-head font-extrabold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
                <span>COBRAR</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </aside>
</body>
</html>