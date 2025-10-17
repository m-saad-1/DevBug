#include<iostream>
using namespace std;
  
int main(){  

// Declares variables of different types (int, float, char)    
  int var1 = 20;
  float var2 = 3.14;
  char var3 = 'a';
  
  int* ptr1 = &var1;
  float* ptr2 = &var2;
  char* ptr3 = &var3;
  
// • Displays addresses and values using pointers  
  cout << "Addresses: " << endl;
  cout << "Address of variable 1: " << &var1 << endl;
  cout << "Address of variable 2: " << &var2 << endl;
  cout << "Address of variable 3: " << &var3 << endl;
  
  cout << "Values: " << endl;
  cout << "Values: " << *ptr1 << endl;
  cout << "Values: " << *ptr2 << endl;
  cout << "Values: " << *ptr3 << endl;

// • Modifies values through pointers
  var1 = 30;
  var2 = 6.28;
  var3 = 'v';
  
  cout << "Value after modification: " << endl;
  cout << "Variable after modification: " << *ptr1 << endl;
  cout << "Variable after modification: " << *ptr2 << endl;
  cout << "Variable after modification: " << *ptr3 << endl;
  
  return 0;
  
} 
