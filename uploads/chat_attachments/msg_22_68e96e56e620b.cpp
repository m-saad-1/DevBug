#include <iostream>
using namespace std;

class Shape{
public:
   virtual void area() = 0;  
   virtual void perimeter() = 0;
   
};

class Rectangle: public Shape{
private: 
   int length;
   int width;
   
public: 
   Rectangle(int l, int w): length(l), width(w){ }
   
   void area() override{
       cout << "Area of the rectangle: " << length * width << endl;
   }
   
   void perimeter() override{
       cout << "Perimeter of the rectangle: " << 2 * (length + width) << endl;
   }
};

class Circle: public Shape{
private:    
    int radius;
    float pi = 3.14;
public: 
    Circle(int r, float phi): radius(r), pi(phi){ }
    
    void area() override{
        cout << "Area of the circle: " << pi * radius * radius << endl;
    }
    
    void perimeter() override{
        cout << "Perimeter of the circle: " << 2 * pi * radius << endl;
    }
};

class Triangle:public Shape{
private: 
   int base;
   int hypotenuse;
   int side;
   
public: 
   Triangle(int b, int h, int s): base(b), hypotenuse(h), side(s){ }
   
   void area() override{
        cout << "Area of the triangle: " << 0.5 * base * hypotenuse << endl;
    }
    
    void perimeter() override{
        cout << "Perimeter of the triangle: " << base + hypotenuse + side << endl;
    }
};

void display(Shape *type){
    
    type->area(); 
    
	type->perimeter();
	
}

int main() {
    Shape *S1;
    
    Rectangle R1(3, 5);
    
    S1 = &R1;
    
    display(&R1);
    
    Shape *S2;
    
    Triangle T1(2, 3, 4);
    
    S2 = &T1;
    
    display(&T1);
    return 0;
}